<?php

namespace App\Support;

use App\Models\ServiceRecord;

class ServiceCertificate
{
    public const TITLES = [
        'baptism' => 'Certificate of Baptism',
        'wedding' => 'Certificate of Marriage',
        'funeral' => 'Certificate of Burial',
        'confirmation' => 'Certificate of Confirmation',
    ];

    /** @return array<string, array<string, string>> */
    public static function sections(ServiceRecord $record): array
    {
        $fields = match ($record->service_type) {
            'baptism' => [
                'Baptized person' => ['Full name' => 'child_full_name', 'Sex' => 'child_sex', 'Date of birth' => 'child_date_of_birth', 'Place of birth' => 'child_place_of_birth', 'Father' => 'father_name', 'Mother' => 'mother_name'],
                'Sponsors' => ['Godfather' => 'godfather_name', 'Godmother' => 'godmother_name'],
            ],
            'wedding' => [
                'Groom' => ['Full name' => 'groom_full_name', 'Age' => 'groom_age', 'Civil status' => 'groom_status', 'Native of' => 'groom_origin', 'Residence' => 'groom_address', 'Father' => 'groom_father_name', 'Mother' => 'groom_mother_maiden_name'],
                'Bride' => ['Full name' => 'bride_full_name', 'Age' => 'bride_age', 'Civil status' => 'bride_status', 'Native of' => 'bride_origin', 'Residence' => 'bride_address', 'Father' => 'bride_father_name', 'Mother' => 'bride_mother_maiden_name'],
                'Witnesses' => ['First witness' => 'godfather_name', 'Residence of first witness' => 'godfather_address', 'Second witness' => 'godmother_name', 'Residence of second witness' => 'godmother_address'],
            ],
            'funeral' => [
                'Burial record' => ['Date of burial' => 'date_of_burial', 'Place of burial' => 'cemetery_location', 'Name of deceased' => 'deceased_full_name', 'Child of / married to / widow(er) of' => 'family_relationship', 'Residence' => 'residence_address', 'Place of death' => 'place_of_death', 'Date of death' => 'date_of_death', 'Age' => 'age', 'Cause of death' => 'cause_of_death', 'Sacraments received' => 'sacraments_received'],
            ],
            'confirmation' => ['Confirmed person' => ['Full name' => 'recipient_full_name']],
            default => [],
        };
        $sections = [];
        $definitions = ServiceRegister::fields($record->service_type);
        foreach ($fields as $heading => $rows) {
            foreach ($rows as $label => $key) {
                $value = ServiceRegister::value($record, $key);
                $definition = $definitions[$key] ?? [];
                if (($definition['type'] ?? null) === 'select') {
                    $value = $definition['options'][$value] ?? $value;
                }
                if (($definition['type'] ?? null) === 'date' && $value !== '') {
                    $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
                    if ($date && $date->format('Y-m-d') === $value) {
                        $value = $date->format('F j, Y');
                    }
                }
                $sections[$heading][$label] = $value;
            }
        }

        return $sections;
    }
}
