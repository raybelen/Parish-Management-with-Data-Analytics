<?php

return [
    'document_disk' => env('APPOINTMENT_DOCUMENT_DISK', 'local'),

    'services' => [
        'baptism' => [
            'label' => 'Baptism',
            'documents' => [
                'birth_certificate' => ['label' => 'Birth certificate', 'required' => true, 'multiple' => false],
                'other_church_documents' => ['label' => 'Other required church documents', 'required' => false, 'multiple' => true],
            ],
        ],
        'wedding' => [
            'label' => 'Wedding',
            'documents' => [
                'baptismal_certificates' => ['label' => 'Baptismal certificates', 'required' => true, 'multiple' => true],
                'confirmation_certificates' => ['label' => 'Confirmation certificates', 'required' => true, 'multiple' => true],
                'marriage_license' => ['label' => 'Marriage license', 'required' => true, 'multiple' => false],
                'pre_marriage_seminar_certificate' => ['label' => 'Pre-marriage seminar certificate', 'required' => true, 'multiple' => false],
                'other_required_documents' => ['label' => 'Other required documents', 'required' => false, 'multiple' => true],
            ],
        ],
        'funeral' => [
            'label' => 'Funeral',
            'documents' => [
                'death_certificate' => ['label' => 'Death certificate', 'required' => true, 'multiple' => false],
                'funeral_home_documentation' => ['label' => 'Funeral home documentation', 'required' => false, 'multiple' => true],
                'other_church_documents' => ['label' => 'Other required church documents', 'required' => false, 'multiple' => true],
            ],
        ],
    ],
];
