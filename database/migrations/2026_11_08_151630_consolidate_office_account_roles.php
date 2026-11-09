<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            foreach (DB::table('users')->whereIn('role', ['staff', 'priest'])->lockForUpdate()->get() as $user) {
                $role = $user->role === 'staff' ? 'admin' : 'user';
                DB::table('users')->where('id', $user->id)->update(['role' => $role, 'remember_token' => null, 'updated_at' => now()]);
                DB::table('audit_logs')->insert([
                    'action' => 'user.role_changed', 'entity_type' => 'App\\Models\\User', 'entity_id' => $user->id,
                    'before' => json_encode(['role' => $user->role], JSON_THROW_ON_ERROR),
                    'after' => json_encode(['role' => $role], JSON_THROW_ON_ERROR),
                    'reason' => 'Office roles consolidated. Staff use Admin; retired priest accounts require explicit role assignment.',
                    'created_at' => now(),
                ]);
            }
            $setting = DB::table('parish_settings')->where('key', 'office')->lockForUpdate()->first();
            if ($setting !== null) {
                $value = json_decode($setting->value, true, 512, JSON_THROW_ON_ERROR);
                if (isset($value['permissions'])) {
                    $value['permissions'] = array_intersect_key($value['permissions'], ['admin' => true]);
                    DB::table('parish_settings')->where('id', $setting->id)->update(['value' => json_encode($value, JSON_THROW_ON_ERROR)]);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new LogicException('Role consolidation cannot be automatically reversed. Assign any required roles explicitly.');
    }
};
