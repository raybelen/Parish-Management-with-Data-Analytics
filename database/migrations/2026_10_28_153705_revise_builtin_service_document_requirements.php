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
        $this->updateRequirements([
            'baptism' => [
                'birth_certificate' => ['label' => 'Photocopy of Birth Certificate with registry number (PSA or Local Civil Registry)', 'required' => true, 'multiple' => false],
                'parents_marriage_contract' => ['label' => 'Marriage Contract, if parents are married', 'required' => false, 'multiple' => false],
            ],
            'wedding' => [
                'psa_birth_certificate' => ['label' => 'PSA Birth Certificate', 'required' => true, 'multiple' => true],
                'updated_baptismal_confirmation_certificate' => ['label' => 'Updated Baptismal & Confirmation Certificate (Purpose: For Marriage)', 'required' => true, 'multiple' => true],
                'marriage_license_or_contract' => ['label' => 'Marriage License, or, if civilly married, Marriage Contract with registry number', 'required' => true, 'multiple' => false],
                'pre_cana_seminar' => ['label' => 'Pre-Cana Seminar', 'required' => true, 'multiple' => false],
            ],
            'funeral' => [
                'death_certificate' => ['label' => 'Photocopy of Death Certificate', 'required' => true, 'multiple' => false],
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->updateRequirements([
            'baptism' => [
                'birth_certificate' => ['label' => 'Birth Certificate', 'required' => true, 'multiple' => false],
                'parents_marriage_contract' => ['label' => 'Marriage contract if parents are married', 'required' => false, 'multiple' => false],
            ],
            'wedding' => [
                'marriage_contract' => ['label' => 'Marriage Contract', 'required' => true, 'multiple' => false],
                'cenomar' => ['label' => 'CENOMAR (Certificate of No Marriage Record)', 'required' => true, 'multiple' => false],
                'baptismal_certificates' => ['label' => 'Baptismal Certificate for Marriage purposes (both bride and groom)', 'required' => true, 'multiple' => true],
                'confirmation_certificates' => ['label' => 'Confirmation certificate for marriage purposes (both bride and groom)', 'required' => true, 'multiple' => true],
                'pre_cana' => ['label' => 'Pre-Cana', 'required' => true, 'multiple' => false],
            ],
            'funeral' => [
                'death_certificate' => ['label' => 'Death certificate', 'required' => true, 'multiple' => false],
            ],
        ]);
    }

    /**
     * @param  array<string, array<string, array{label: string, required: bool, multiple: bool}>>  $requirementsByService
     */
    private function updateRequirements(array $requirementsByService): void
    {
        foreach ($requirementsByService as $service => $requirements) {
            DB::table('service_types')
                ->where('slug', $service)
                ->update(['requirements' => json_encode($requirements, JSON_THROW_ON_ERROR)]);
        }
    }
};
