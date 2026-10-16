<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Http\Requests\Concerns\ValidatesDoctorData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * El usuario edita sus propios datos. No puede cambiar su rol, su estado ni su visitador.
 */
class UpdateProfileRequest extends FormRequest
{
    use ValidatesDoctorData;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->user();

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            ...$this->doctorDataRules($user->hasRole(Role::Doctor)),
        ];
    }
}
