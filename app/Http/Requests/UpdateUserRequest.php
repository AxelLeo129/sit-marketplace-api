<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Http\Requests\Concerns\ValidatesDoctorData;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Editar un usuario. Solo se validan los campos enviados.
 *
 * - El visitador no puede cambiar el rol ni el visitador de sus médicos.
 * - Nadie puede cambiar su propio rol ni desactivarse (evita quedarse sin administrador).
 */
class UpdateUserRequest extends FormRequest
{
    use ValidatesDoctorData;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->targetUser());
    }

    protected function prepareForValidation(): void
    {
        if ($this->user()->hasRole(Role::SalesRep)) {
            $this->merge(['role' => Role::Doctor->value, 'sales_rep_id' => $this->user()->id]);
        }
    }

    public function rules(): array
    {
        $user = $this->targetUser();
        $isSelf = $this->user()->is($user);
        $isDoctor = $this->input('role', $user->role->value) === Role::Doctor->value;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            // El administrador puede asignar una contraseña nueva; se cierran las sesiones del usuario.
            'password' => ['sometimes', 'required', 'string', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'role' => [Rule::prohibitedIf($isSelf), 'sometimes', Rule::enum(Role::class)],
            'is_active' => [Rule::prohibitedIf($isSelf), 'sometimes', 'boolean'],
            'collegiate_number' => $isDoctor
                ? ['sometimes', 'required', 'string', 'max:30', Rule::unique('users')->ignore($user)]
                : ['prohibited'],
            'sales_rep_id' => [$isDoctor ? 'nullable' : 'prohibited', 'integer', Rule::exists('users', 'id')->where('role', Role::SalesRep->value)],
            ...$this->doctorDataRules($isDoctor),
        ];
    }

    private function targetUser(): User
    {
        return $this->route('user');
    }
}
