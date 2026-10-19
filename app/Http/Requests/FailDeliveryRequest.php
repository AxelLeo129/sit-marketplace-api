<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * El mensajero reporta que no pudo entregar.
 */
class FailDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('completeDelivery', $this->route('order'));
    }

    public function rules(): array
    {
        return [
            // Motivo obligatorio para que el despachador decida si reasigna o cancela.
            'notes' => ['required', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return ['notes' => 'motivo'];
    }
}
