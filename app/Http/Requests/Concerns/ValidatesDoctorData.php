<?php

namespace App\Http\Requests\Concerns;

/**
 * Reglas de los datos propios de un médico: perfil de clínica y especialidades.
 */
trait ValidatesDoctorData
{
    /**
     * @param  bool  $isDoctor  Si el usuario es (o será) médico. Si no lo es, estos campos se rechazan.
     * @return array<string, mixed>
     */
    protected function doctorDataRules(bool $isDoctor): array
    {
        $allowed = $isDoctor ? 'nullable' : 'prohibited';

        return [
            'doctor_profile' => [$allowed, 'array'],
            'doctor_profile.clinic_name' => ['nullable', 'string', 'max:255'],
            'doctor_profile.clinic_phone' => ['nullable', 'string', 'max:20'],
            'doctor_profile.clinic_address' => ['nullable', 'string', 'max:255'],
            'doctor_profile.keeps_clinical_record' => ['boolean'],
            'doctor_profile.show_alerts' => ['boolean'],
            'category_ids' => [$allowed, 'array'],
            'category_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
        ];
    }
}
