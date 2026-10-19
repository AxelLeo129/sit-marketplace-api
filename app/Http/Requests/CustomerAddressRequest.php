<?php

namespace App\Http\Requests;

use App\Enums\AddressType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Crear o editar una dirección de facturación o de entrega.
 */
class CustomerAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El permiso lo valida la ruta (can:manage-customers).
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(AddressType::class)],
            'country' => ['nullable', 'string', 'max:100'],
            'department' => ['required', 'string', 'max:100'],
            'municipality' => ['required', 'string', 'max:100'],
            'street' => ['required', 'string', 'max:255'],
            'apartment' => ['nullable', 'string', 'max:255'],
            'neighborhood' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'phone' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function attributes(): array
    {
        return [
            'type' => 'tipo de dirección',
            'department' => 'departamento',
            'municipality' => 'municipio',
            'street' => 'calle',
            'apartment' => 'apartamento',
            'neighborhood' => 'colonia o zona',
        ];
    }
}
