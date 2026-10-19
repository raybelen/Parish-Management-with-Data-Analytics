<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\ParishSetting;
use App\Models\Payment;
use App\Models\ServiceRecord;
use App\Models\ServiceType;
use App\Models\User;
use App\Support\AppointmentParticipants;
use App\Support\OfficeAudit;
use App\Support\OfficeSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SeedAnalyticsDemo extends Command
{
    protected $signature = 'analytics:seed-demo {--months=60 : Number of complete synthetic months} {--with-account : Create a local-only demo account with the documented test credentials}';

    protected $description = 'Create repeatable synthetic appointments in an empty, explicitly enabled demo database';

    public function handle(): int
    {
        if (! OfficeSettings::demo() || DB::getDriverName() !== 'sqlite' || (DB::connection()->getDatabaseName() !== ':memory:' && ! str_ends_with(str_replace('\\', '/', DB::connection()->getDatabaseName()), '/analytics-demo.sqlite'))) {
            $this->error('Use an isolated analytics-demo.sqlite database, APP_ENV=demo (or local/testing), and ANALYTICS_DEMO=true. Production and ordinary databases are refused.');

            return self::FAILURE;
        }
        $months = filter_var($this->option('months'), FILTER_VALIDATE_INT);
        if ($months === false || $months < 36 || $months > 120 || Appointment::query()->exists() || Payment::query()->exists() || ServiceRecord::query()->exists()) {
            $this->error('Choose 36–120 months and an empty demo database. Existing appointments, payments and records are never overwritten.');

            return self::FAILURE;
        }
        DB::transaction(function () use ($months): void {
            $start = now()->startOfMonth()->subMonths($months);
            $settings = ParishSetting::query()->where('key', 'office')->firstOrFail();
            $settings->update(['value' => [...($settings->value ?? []), 'analytics_start' => $start->toDateString(), 'training_months' => $months]]);
            foreach (ServiceType::query()->whereIn('slug', ['baptism', 'wedding', 'funeral'])->get() as $service) {
                for ($month = 0; $month < $months; $month++) {
                    $count = max(3, (int) round(12 + 6 * sin(2 * M_PI * $month / 12) + $month * 0.12 + (($month * 7) % 5) - 2));
                    for ($index = 0; $index < $count; $index++) {
                        $date = $start->copy()->addMonths($month)->addDays($index % 25)->setTime(10, 0);
                        $status = $index === 0 ? 'cancelled' : ($index === 1 ? 'no_show' : 'completed');
                        $name = 'Demo '.$service->name.' '.$month.'-'.$index;
                        $details = match ($service->slug) {
                            'baptism' => ['child_full_name' => $name, 'child_date_of_birth' => $date->copy()->subYear()->toDateString()],
                            'wedding' => ['bride_full_name' => $name.' A', 'groom_full_name' => $name.' B'],
                            default => ['deceased_full_name' => $name],
                        };
                        $appointment = Appointment::create([
                            'reference_number' => 'DEMO-'.$service->slug.'-'.$month.'-'.$index, 'source' => 'synthetic', 'status' => $status,
                            'client_full_name' => 'Demo contact '.$index, 'client_contact_number' => '09000000000', 'client_contact_key' => '09000000000',
                            'client_address' => 'Simulated address', 'preferred_contact_method' => 'phone', 'service_type' => $service->slug,
                            'preferred_date' => $date->toDateString(), 'preferred_time' => '10:00', 'preferred_church' => 'San Isidro Labrador Chapel',
                            'starts_at' => $date, 'ends_at' => $date->copy()->addHour(), 'reserved_until' => $date->copy()->addMinutes(90),
                            'confirmed_church' => 'San Isidro Labrador Chapel', 'completed_at' => $status === 'completed' ? $date->copy()->addHour() : null,
                            'fee_cents' => 50000 + ($month % 7) * 2500, 'service_details' => $details, 'documents' => [], 'requirements' => [],
                            'outcome' => 'Synthetic demonstration record',
                        ]);
                        $appointment->created_at = $date->copy()->subDays(5);
                        $appointment->save();
                        AppointmentParticipants::sync($appointment);
                        if ($status === 'completed') {
                            ServiceRecord::create(['appointment_id' => $appointment->id, 'record_number' => 'REC-'.$appointment->reference_number, 'source' => 'synthetic', 'service_type' => $service->slug, 'service_date' => $date->toDateString(), 'participant_names' => $name, 'church' => 'San Isidro Labrador Chapel', 'details' => $details]);
                            if ($index % 9 !== 0) {
                                $payment = $appointment->payments()->create(['kind' => 'payment', 'amount_cents' => $index % 5 === 0 ? (int) ($appointment->fee_cents / 2) : $appointment->fee_cents, 'idempotency_key' => (string) Str::uuid(), 'method' => 'cash', 'paid_at' => $date, 'notes' => 'Synthetic demo payment']);
                                if ($index % 8 === 0) {
                                    $appointment->payments()->create(['kind' => 'refund', 'original_payment_id' => $payment->id, 'amount_cents' => 5000, 'idempotency_key' => (string) Str::uuid(), 'method' => 'cash', 'paid_at' => $date->copy()->addDay(), 'notes' => 'Synthetic partial refund']);
                                }
                            }
                        }
                    }
                }
            }
            OfficeAudit::record('demo.seeded', null, [], ['months' => $months, 'source' => 'synthetic']);
            if ($this->option('with-account')) {
                User::factory()->superAdmin()->withTwoFactor()->create([
                    'name' => 'Demo Administrator', 'email' => 'demo@example.test', 'password' => 'DemoParish!2026',
                ]);
            }
        });
        $this->info('Demo data generated. All records are synthetic and isolated from live analytics.');
        if ($this->option('with-account')) {
            $this->comment('Demo account: demo@example.test / DemoParish!2026. Authenticator test secret: JBSWY3DPEHPK3PXP. Use only on localhost.');
        }

        return self::SUCCESS;
    }
}
