<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Http\Requests\Concerns\ValidatesDoctorData;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Crear un usuario. El visitador solo puede crear médicos, que quedan asignados a él.
 */
class StoreUserRequest extends FormRequest
{
    use ValidatesDoctorData;

    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    /**
     * Si quien crea es un visitador, se ignora lo que envíe en rol y visitador.
     */
    protected function prepareForValidation(): void
    {
        if ($this->user()->hasRole(Role::SalesRep)) {
            $this->merge(['role' => Role::Doctor->value, 'sales_rep_id' => $this->user()->id]);
        }
    }

    public function rules(): array
    {
        $isDoctor = $this->input('role') === Role::Doctor->value;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'role' => ['required', Rule::enum(Role::class)],
            'is_active' => ['boolean'],
            'collegiate_number' => [$isDoctor ? 'required' : 'prohibited', 'string', 'max:30', 'unique:users,collegiate_number'],
            'sales_rep_id' => [$isDoctor ? 'nullable' : 'prohibited', 'integer', Rule::exists('users', 'id')->where('role', Role::SalesRep->value)],
            ...$this->doctorDataRules($isDoctor),
        ];
    }
}
