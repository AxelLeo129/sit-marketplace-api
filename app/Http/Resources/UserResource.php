<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representación pública de un usuario.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'collegiate_number' => $this->collegiate_number,
            'phone' => $this->phone,
            'birth_date' => $this->birth_date?->toDateString(),
            'avatar_path' => $this->avatar_path,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'sales_rep_id' => $this->sales_rep_id,
            'is_active' => $this->is_active,
            'doctor_profile' => $this->whenLoaded('doctorProfile'),
            'created_at' => $this->created_at,
        ];
    }
}
