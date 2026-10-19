<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * El mensajero confirma la entrega.
 */
class DeliverOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('completeDelivery', $this->route('order'));
    }

    public function rules(): array
    {
        return [
            'received_by' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'received_by' => 'nombre de quien recibe',
            'notes' => 'observaciones',
        ];
    }
}
