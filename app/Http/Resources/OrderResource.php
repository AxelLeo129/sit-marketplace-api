<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'prescription_id' => $this->prescription_id,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'billing_address' => new CustomerAddressResource($this->whenLoaded('billingAddress')),
            'shipping_address' => new CustomerAddressResource($this->whenLoaded('shippingAddress')),
            'created_by' => $this->whenLoaded('creator', fn () => ['id' => $this->creator->id, 'name' => $this->creator->name]),
            'payment' => [
                'method' => $this->payment_method->value,
                'method_label' => $this->payment_method->label(),
                'reference' => $this->payment_reference,
                'cardholder_name' => $this->cardholder_name,
                'card_last_four' => $this->card_last_four,
            ],
            'vat_rate' => $this->vat_rate,
            'total' => $this->total,
            'invoice' => [
                'tax_id' => $this->invoice_tax_id,
                'number' => $this->invoice_number,
                'invoiced_at' => $this->invoiced_at,
            ],
            'delivery' => [
                'courier' => $this->whenLoaded('courier', fn () => $this->courier
                    ? ['id' => $this->courier->id, 'name' => $this->courier->name]
                    : null),
                'assigned_at' => $this->assigned_at,
                'dispatched_at' => $this->dispatched_at,
                'delivered_at' => $this->delivered_at,
                'received_by' => $this->received_by,
                'notes' => $this->delivery_notes,
            ],
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'can' => [
                'cancel' => $request->user()->can('cancel', $this->resource),
            ],
            'created_at' => $this->created_at,
        ];
    }
}
