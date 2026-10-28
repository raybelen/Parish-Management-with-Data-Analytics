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
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->updateRequirements([
            'baptism' => [
                'birth_certificate' => ['label' => 'Birth certificate', 'required' => true, 'multiple' => false],
                'other_church_documents' => ['label' => 'Other required church documents', 'required' => false, 'multiple' => true],
            ],
            'wedding' => [
                'baptismal_certificates' => ['label' => 'Baptismal certificates', 'required' => true, 'multiple' => true],
                'confirmation_certificates' => ['label' => 'Confirmation certificates', 'required' => true, 'multiple' => true],
                'marriage_license' => ['label' => 'Marriage license', 'required' => true, 'multiple' => false],
                'pre_marriage_seminar_certificate' => ['label' => 'Pre-marriage seminar certificate', 'required' => true, 'multiple' => false],
                'other_required_documents' => ['label' => 'Other required documents', 'required' => false, 'multiple' => true],
            ],
            'funeral' => [
                'death_certificate' => ['label' => 'Death certificate', 'required' => true, 'multiple' => false],
                'funeral_home_documentation' => ['label' => 'Funeral home documentation', 'required' => false, 'multiple' => true],
                'other_church_documents' => ['label' => 'Other required church documents', 'required' => false, 'multiple' => true],
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
