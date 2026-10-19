<?php

namespace App\Http\Resources;

use App\Enums\Role;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * Línea de pedido. El desglose financiero se muestra según el rol:
 * facturación ve neto e IVA; solo el administrador ve costo, margen y comisiones.
 *
 * @mixin OrderItem
 */
class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $canSeeFinancials = Gate::allows('view-financials');
        $canSeeTaxes = $canSeeFinancials || $request->user()->hasRole(Role::Billing);

        return [
            'id' => $this->id,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'presentation' => $this->product->relationLoaded('presentation')
                    ? new PresentationResource($this->product->presentation)
                    : null,
            ]),
            'origin' => $this->origin->value,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'line_total' => $this->line_total,
            $this->mergeWhen($canSeeTaxes, fn () => [
                'net_amount' => $this->net_amount,
                'vat_amount' => $this->vat_amount,
            ]),
            $this->mergeWhen($canSeeFinancials, fn () => [
                'unit_cost' => $this->unit_cost,
                'card_fee_amount' => $this->card_fee_amount,
                'gross_margin' => $this->gross_margin,
                'purchase_number' => $this->purchase_number,
                'commissions' => CommissionResource::collection($this->whenLoaded('commissions')),
            ]),
        ];
    }
}
