<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Services\ImageStorage;
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
            'avatar_url' => app(ImageStorage::class)->url($this->avatar_path),
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'is_active' => $this->is_active,
            'sales_rep' => $this->whenLoaded('salesRep', fn () => $this->salesRep
                ? ['id' => $this->salesRep->id, 'name' => $this->salesRep->name]
                : null),
            'doctor_profile' => new DoctorProfileResource($this->whenLoaded('doctorProfile')),
            'specialties' => CategoryResource::collection($this->whenLoaded('specialties')),
            'created_at' => $this->created_at,
        ];
    }
}
