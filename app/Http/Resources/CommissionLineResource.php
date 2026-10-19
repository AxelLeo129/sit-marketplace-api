<?php

namespace App\Http\Resources;

use App\Models\Commission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Comisión ganada en una línea de un pedido entregado, vista por su beneficiario.
 * No incluye costos ni márgenes.
 *
 * @mixin Commission
 */
class CommissionLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => (int) $this->order_id,
            // Viene de un join, sin cast del modelo: se convierte a fecha para serializarla igual que el resto.
            'delivered_at' => Carbon::parse($this->delivered_at),
            'product_name' => $this->product_name,
            'quantity' => (int) $this->quantity,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'rate' => $this->rate,
            'amount' => $this->amount,
        ];
    }
}
