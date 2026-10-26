<?php

namespace Tests\Feature;

use App\Actions\CreateOfficeBackup;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\MonthlyStatistic;
use App\Models\Payment;
use App\Models\Schedule;
use App\Models\ServiceRecord;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OfficeRecoveryTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_recovery_restores_relationships_and_private_documents(): void
    {
        Storage::fake('local');
        $this->app->instance('env', 'recovery');
        $session = Schedule::factory()->create(['category' => 'Group Baptism', 'group_capacity' => 10, 'group_fee' => '250.00']);
        $appointment = Appointment::factory()->create(['group_baptism_session_id' => $session->id]);
        $document = 'appointments/'.$appointment->reference_number.'/certificate.pdf';
        Storage::disk('local')->put($document, 'Private certificate content');
        $appointment->update(['documents' => ['birth_certificate' => [['path' => $document, 'original_name' => 'certificate.pdf']]]]);
        $payment = Payment::factory()->create(['appointment_id' => $appointment->id]);
        $record = ServiceRecord::factory()->create(['appointment_id' => $appointment->id, 'source' => 'operational']);
        $statistic = MonthlyStatistic::factory()->create(['count' => 168, 'reference' => 'Verified parish register']);
        $path = app(CreateOfficeBackup::class)->handle();
        foreach (array_reverse(CreateOfficeBackup::TABLES) as $table) {
            DB::table($table)->delete();
        }
        Storage::disk('local')->delete($document);

        $this->artisan('office:restore', ['path' => $path])->assertSuccessful();

        $this->assertModelExists($appointment);
        $this->assertSame($session->id, $appointment->fresh()->group_baptism_session_id);
        $this->assertSame(10, $session->fresh()->group_capacity);
        $this->assertSame($appointment->id, $payment->fresh()->appointment_id);
        $this->assertSame($record->id, $appointment->fresh()->serviceRecord->id);
        $this->assertSame(168, $statistic->fresh()->count);
        $this->assertSame('Verified parish register', $statistic->fresh()->reference);
        $this->assertSame('Private certificate content', Storage::disk('local')->get($document));
    }

    public function test_recovery_refuses_an_audit_only_database(): void
    {
        $this->app->instance('env', 'recovery');
        $audit = AuditLog::factory()->create();

        $this->artisan('office:restore', ['path' => 'office-backups/missing.json.enc'])->assertFailed();

        $this->assertModelExists($audit);
    }

    public function test_recovery_never_overwrites_existing_documents(): void
    {
        Storage::fake('local');
        $this->app->instance('env', 'recovery');
        $document = 'appointments/RECOVERY/certificate.pdf';
        Storage::disk('local')->put($document, 'Existing original');
        $tables = array_fill_keys(CreateOfficeBackup::TABLES, []);
        $path = 'office-backups/recovery.json.enc';
        Storage::disk('local')->put($path, Crypt::encryptString(json_encode(['version' => 1, 'tables' => $tables, 'documents' => [$document => base64_encode('Replacement')]])));

        $this->artisan('office:restore', ['path' => $path])->assertFailed();

        $this->assertSame('Existing original', Storage::disk('local')->get($document));
        $this->assertDatabaseCount('service_types', 3);
    }
}
