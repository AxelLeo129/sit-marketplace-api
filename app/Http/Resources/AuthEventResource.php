<?php

namespace App\Http\Resources;

use App\Models\AuthEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuthEvent
 */
class AuthEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'is_warning' => $this->type->isWarning(),
            'user' => $this->whenLoaded('user', fn () => $this->user
                ? ['id' => $this->user->id, 'name' => $this->user->name, 'role_label' => $this->user->role->label()]
                : null),
            // Lo que se escribió para entrar (correo o colegiado).
            'login' => $this->login,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            // Motivo técnico, p. ej. "bad_password" o "account_locked".
            'detail' => $this->detail,
            'created_at' => $this->created_at,
        ];
    }
}
