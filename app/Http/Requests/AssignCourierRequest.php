<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * El despachador asigna un mensajero al pedido.
 */
class AssignCourierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assign', $this->route('order'));
    }

    public function rules(): array
    {
        return [
            // Solo mensajeros activos.
            'courier_id' => [
                'required', 'integer',
                Rule::exists('users', 'id')
                    ->where('role', Role::Courier->value)
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    public function attributes(): array
    {
        return ['courier_id' => 'mensajero'];
    }
}
