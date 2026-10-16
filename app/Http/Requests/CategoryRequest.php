<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Crear o editar una categoría.
 */
class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El permiso lo valida la ruta (can:manage-catalog).
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('categories')->ignore($this->route('category'))],
        ];
    }
}
