<?php

namespace App\Http\Resources;

use App\Models\CustomerAddress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CustomerAddress
 */
class CustomerAddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'country' => $this->country,
            'department' => $this->department,
            'municipality' => $this->municipality,
            'street' => $this->street,
            'apartment' => $this->apartment,
            'neighborhood' => $this->neighborhood,
            'postal_code' => $this->postal_code,
            'phone' => $this->phone,
            // Dirección en una línea, lista para mostrar o imprimir.
            'full_address' => collect([
                $this->street, $this->apartment, $this->neighborhood, $this->municipality, $this->department,
            ])->filter()->implode(', '),
        ];
    }
}
