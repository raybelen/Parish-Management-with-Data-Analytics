<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_types', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 30)->unique();
            $table->string('name');
            $table->unsignedInteger('duration_minutes')->default(60);
            $table->unsignedInteger('buffer_minutes')->default(30);
            $table->unsignedBigInteger('fee_cents')->default(0);
            $table->json('requirements');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        foreach (config('appointments.services') as $slug => $service) {
            DB::table('service_types')->insert([
                'slug' => $slug, 'name' => $service['label'], 'duration_minutes' => $slug === 'wedding' ? 90 : 60,
                'buffer_minutes' => 30, 'fee_cents' => 0, 'requirements' => json_encode($service['documents']),
                'active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        Schema::create('personnel', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->foreignId('user_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::create('parish_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->json('value');
            $table->timestamps();
        });
        DB::table('parish_settings')->insert(['key' => 'office', 'value' => '{}', 'created_at' => now(), 'updated_at' => now()]);
        Schema::table('appointments', function (Blueprint $table): void {
            $table->string('source', 20)->default('operational');
            $table->foreignId('personnel_id')->nullable()->constrained('personnel')->restrictOnDelete();
            $table->string('confirmed_church')->nullable();
            $table->dateTime('starts_at')->nullable()->index();
            $table->dateTime('ends_at')->nullable();
            $table->dateTime('reserved_until')->nullable();
            $table->unsignedInteger('buffer_minutes')->default(30);
            $table->unsignedBigInteger('fee_cents')->default(0);
            $table->dateTime('completed_at')->nullable();
            $table->string('outcome')->nullable();
            $table->text('office_notes')->nullable();
            $table->json('requirements')->nullable();
            $table->json('physical_documents')->nullable();
            $table->json('online_verification')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->index(['source', 'status', 'completed_at']);
        });
        Schema::create('appointment_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->restrictOnDelete();
            $table->string('role', 50);
            $table->string('full_name');
            $table->date('date_of_birth')->nullable();
            $table->timestamps();
        });
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('original_payment_id')->nullable()->constrained('payments')->restrictOnDelete();
            $table->uuid('idempotency_key')->unique();
            $table->string('kind', 20);
            $table->unsignedBigInteger('amount_cents');
            $table->string('method', 30);
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('paid_at')->index();
            $table->timestamps();
        });
        Schema::create('service_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('appointment_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('record_number')->unique();
            $table->string('source', 20)->default('archival')->index();
            $table->string('service_type', 30);
            $table->date('service_date')->index();
            $table->string('participant_names');
            $table->json('participants')->nullable();
            $table->json('details')->nullable();
            $table->string('church');
            $table->string('officiant')->nullable();
            $table->string('register_book')->nullable();
            $table->string('register_page')->nullable();
            $table->string('register_entry')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('archived_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action')->index();
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['entity_type', 'entity_id']);
        });
        Schema::create('forecast_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 20);
            $table->string('service_type', 30)->nullable();
            $table->string('status', 30);
            $table->text('message')->nullable();
            $table->json('configuration');
            $table->json('history');
            $table->json('results')->nullable();
            $table->json('evaluation')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('office_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('appointment_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('event');
            $table->string('message');
            $table->string('deduplication_key')->nullable()->unique();
            $table->dateTime('read_at')->nullable();
            $table->foreignId('read_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('schedule_blocks', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('church')->nullable();
            $table->foreignId('personnel_id')->nullable()->constrained('personnel')->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['schedule_blocks', 'office_notifications', 'forecast_runs', 'audit_logs', 'service_records', 'payments', 'appointment_participants'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropForeign(['personnel_id']);
            $table->dropIndex(['source', 'status', 'completed_at']);
            $table->dropIndex(['starts_at']);
            $table->dropColumn(['source', 'personnel_id', 'confirmed_church', 'starts_at', 'ends_at', 'reserved_until', 'buffer_minutes', 'fee_cents', 'completed_at', 'outcome', 'office_notes', 'requirements', 'physical_documents', 'online_verification', 'version']);
        });
        Schema::dropIfExists('parish_settings');
        Schema::dropIfExists('personnel');
        Schema::dropIfExists('service_types');
    }
};
