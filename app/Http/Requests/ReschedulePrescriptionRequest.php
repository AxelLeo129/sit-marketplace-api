<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * El paciente pidió que se le llame en otro momento.
 */
class ReschedulePrescriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('handleCall', $this->route('prescription'));
    }

    public function rules(): array
    {
        return [
            'callback_at' => ['required', 'date', 'after:now'],
        ];
    }

    public function attributes(): array
    {
        return ['callback_at' => 'fecha de la llamada'];
    }
}
