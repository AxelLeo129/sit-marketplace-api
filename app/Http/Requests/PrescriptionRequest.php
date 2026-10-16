<?php

namespace App\Http\Requests;

use App\Models\Prescription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Crear o editar una receta. Al editar, la lista de medicamentos se reemplaza completa.
 */
class PrescriptionRequest extends FormRequest
{
    /**
     * Crear: solo médicos. Editar: el autor, mientras la receta siga abierta.
     */
    public function authorize(): bool
    {
        $prescription = $this->route('prescription');

        return $prescription
            ? $this->user()->can('update', $prescription)
            : $this->user()->can('create', Prescription::class);
    }

    public function rules(): array
    {
        return [
            'patient_name' => ['required', 'string', 'max:255'],
            'patient_phone' => ['required', 'string', 'max:20'],
            'patient_email' => ['nullable', 'email', 'max:255'],
            'symptoms' => ['nullable', 'string', 'max:2000'],
            'diagnosis' => ['nullable', 'string', 'max:2000'],
            'observations' => ['nullable', 'string', 'max:2000'],
            'next_appointment_on' => ['nullable', 'date', 'after:today'],
            'allows_call_center' => ['required', 'boolean'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            // Solo productos activos y no eliminados.
            'items.*.product_id' => [
                'required', 'integer', 'distinct',
                Rule::exists('products', 'id')->where('is_active', true)->whereNull('deleted_at'),
            ],
            'items.*.dosage_instructions' => ['required', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'items' => 'medicamentos',
            'items.*.product_id' => 'medicamento',
            'items.*.dosage_instructions' => 'indicaciones',
        ];
    }
}
