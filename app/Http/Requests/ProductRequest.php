<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Crear o editar un producto. La imagen se sube aparte (ProductImageController).
 */
class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El permiso lo valida la ruta (can:manage-catalog).
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'presentation_id' => ['required', 'integer', 'exists:presentations,id'],
            'lab_id' => ['required', 'integer', 'exists:labs,id'],
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999'],
            'cost' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999'],
            'stock' => ['required', 'integer', 'min:0'],
            'warehouse' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
            'is_regulated' => ['boolean'],
            'observations' => ['nullable', 'string', 'max:2000'],
            'category_ids' => ['array'],
            'category_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
        ];
    }
}
