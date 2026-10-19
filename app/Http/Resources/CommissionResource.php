<?php

namespace App\Http\Resources;

use App\Models\Commission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Commission
 */
class CommissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            // Null mientras no haya beneficiario (p. ej. mensajero aún sin asignar).
            'beneficiary' => $this->whenLoaded('beneficiary', fn () => $this->beneficiary
                ? ['id' => $this->beneficiary->id, 'name' => $this->beneficiary->name]
                : null),
            'rate' => $this->rate,
            'amount' => $this->amount,
        ];
    }
}
