<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Crear o editar una presentación, p. ej. "Caja" de 30 "tabletas".
 */
class PresentationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El permiso lo valida la ruta (can:manage-catalog).
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'unit' => ['required', 'string', 'max:50'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
        ];
    }
}
