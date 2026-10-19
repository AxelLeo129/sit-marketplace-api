<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Mensajero, con lo mínimo que necesita el despachador.
 *
 * @mixin User
 */
class CourierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'pending_deliveries_count' => $this->whenCounted('pending_deliveries_count'),
        ];
    }
}
