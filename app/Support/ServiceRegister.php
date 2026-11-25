<?php

namespace App\Support;

use App\Models\ServiceRecord;
use Illuminate\Support\Carbon;

class ServiceRegister
{
    /** @return array<string, array<string, mixed>> */
    public static function fields(string $service): array
    {
        if (! in_array($service, ['baptism', 'wedding', 'funeral', 'confirmation'], true)) {
            return [];
        }
        $fields = config('appointments.details.'.$service, []);
        unset($fields['anointing_of_sick']);
        if ($service === 'funeral') {
            $fields['date_of_burial'] = ['label' => 'Date of burial', 'type' => 'date', 'rules' => ['nullable', 'date_format:Y-m-d']];
            foreach (['family_relationship' => 'Child of / married to / widow(er) of', 'place_of_death' => 'Place of death', 'sacraments_received' => 'Sacraments received'] as $key => $label) {
                $fields[$key] = ['label' => $label, 'group' => 'Burial certificate details', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:1000']];
            }
        }

        return $fields;
    }

    /** @return array<string, string> */
    public static function columns(string $service): array
    {
        $keys = match ($service) {
            'baptism' => ['service_year', 'service_month_day', 'child_full_name', 'birth_year', 'birth_month_day', 'child_place_of_birth', 'parents', 'godparents', 'officiant'],
            'wedding' => ['service_year', 'service_month_day', 'groom_full_name', 'bride_full_name', 'groom_status', 'bride_status', 'groom_age', 'bride_age', 'groom_origin', 'bride_origin', 'groom_address', 'bride_address', 'groom_father_name', 'groom_mother_maiden_name', 'bride_father_name', 'bride_mother_maiden_name', 'godfather_name', 'godmother_name', 'godfather_address', 'godmother_address', 'officiant'],
            'funeral' => ['deceased_full_name', 'death_month_day', 'death_year', 'date_of_burial', 'age', 'civil_status', 'contact_full_name', 'contact_relationship', 'residence_address', 'cause_of_death', 'cemetery_location', 'officiant'],
            'confirmation' => ['service_year', 'service_month_day', 'recipient_full_name', 'age', 'address', 'place_of_baptism', 'father_name', 'mother_name', 'godfather_name', 'godmother_name', 'officiant'],
            default => [],
        };
        $event = match ($service) {
            'baptism' => 'Baptism', 'wedding' => 'Marriage', default => 'Confirmation',
        };
        $labels = [
            'parents' => 'Parents', 'godparents' => 'Godparents',
            'service_year' => $event.' year', 'service_month_day' => $event.' month / day',
            'birth_year' => 'Birthday year', 'birth_month_day' => 'Birthday month / day',
            'death_year' => 'Year of death', 'death_month_day' => 'Date of death (month / day)',
            'officiant' => 'Name of minister / priest', 'place_of_baptism' => 'Baptism parish',
        ];
        $fields = self::fields($service);
        $columns = [];
        foreach ($keys as $key) {
            $columns[$key] = $labels[$key] ?? $fields[$key]['label'];
        }

        return $columns;
    }

    public static function value(ServiceRecord $record, string $key): string
    {
        if ($key === 'officiant') {
            return $record->officiant ?? '';
        }
        if (in_array($key, ['service_year', 'service_month_day'], true)) {
            return $record->service_date?->format($key === 'service_year' ? 'Y' : 'm/d') ?? '';
        }
        $dateKey = match ($key) {
            'birth_year', 'birth_month_day' => 'child_date_of_birth',
            'death_year', 'death_month_day' => 'date_of_death',
            default => null,
        };
        if ($dateKey !== null) {
            $date = self::value($record, $dateKey);
            try {
                return $date === '' ? '' : Carbon::parse($date)->format(str_ends_with($key, '_year') ? 'Y' : 'm/d');
            } catch (\Exception) {
                return '';
            }
        }
        if (array_key_exists($key, $record->details ?? [])) {
            return is_scalar($record->details[$key]) ? (string) $record->details[$key] : '';
        }
        $role = match ($key) {
            'child_full_name', 'child_date_of_birth' => 'child', 'groom_full_name' => 'groom',
            'bride_full_name' => 'bride', 'deceased_full_name' => 'deceased',
            'recipient_full_name' => 'recipient', 'father_name' => 'father', 'mother_name' => 'mother',
            'godfather_name' => 'godfather', 'godmother_name' => 'godmother', default => null,
        };
        if ($role !== null) {
            $participant = collect($record->participants ?? [])->firstWhere('role', $role);
            $value = $participant[$key === 'child_date_of_birth' ? 'date_of_birth' : 'full_name'] ?? null;
            if ($value !== null) {
                return (string) $value;
            }
        }
        if (in_array($key, ['child_full_name', 'deceased_full_name', 'recipient_full_name'], true)) {
            return $record->participant_names ?? '';
        }

        return '';
    }
}
