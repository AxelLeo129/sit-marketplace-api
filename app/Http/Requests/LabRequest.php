<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Crear o editar un laboratorio.
 */
class LabRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El permiso lo valida la ruta (can:manage-catalog).
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('labs')->ignore($this->route('lab'))],
        ];
    }
}
