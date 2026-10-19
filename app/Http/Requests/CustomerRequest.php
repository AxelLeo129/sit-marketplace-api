<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Crear o editar un cliente.
 */
class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El permiso lo valida la ruta (can:manage-customers).
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // NIT guatemalteco (dígitos y, opcionalmente, una K al final) o "CF" para consumidor final.
            'tax_id' => ['required', 'string', 'max:20', 'regex:/^(CF|\d{1,12}-?[\dK])$/i'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'gender' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date', 'before:today'],
        ];
    }

    public function attributes(): array
    {
        return ['tax_id' => 'NIT'];
    }

    /**
     * Normaliza el NIT a mayúsculas y sin espacios ("cf" → "CF").
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('tax_id'))) {
            $this->merge(['tax_id' => strtoupper(str_replace(' ', '', $this->input('tax_id')))]);
        }
    }
}
