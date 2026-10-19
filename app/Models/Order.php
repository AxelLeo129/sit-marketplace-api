<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * Pedido de medicamentos: lo crea el call-center, lo factura facturación,
 * lo asigna el despachador y lo entrega el mensajero.
 */
#[Fillable([
    'prescription_id', 'customer_id', 'billing_address_id', 'shipping_address_id', 'created_by', 'status',
    'payment_method', 'payment_reference', 'cardholder_name', 'card_last_four',
    'vat_rate', 'card_fee_rate', 'total',
    'invoice_tax_id', 'invoice_number', 'invoiced_at', 'invoiced_by',
    'courier_id', 'assigned_at', 'dispatched_at', 'delivered_at', 'received_by', 'delivery_notes',
])]
class Order extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_method' => PaymentMethod::class,
            'vat_rate' => 'decimal:2',
            'card_fee_rate' => 'decimal:2',
            'total' => 'decimal:2',
            'invoiced_at' => 'datetime',
            'assigned_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * Pedidos que el usuario puede ver:
     * administrador, call-center y facturación todos; el despachador los
     * ya facturados; el mensajero solo los que tiene asignados.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        match ($user->role) {
            Role::Admin, Role::CallCenter, Role::Billing => null,
            Role::Dispatcher => $query->whereNot('status', OrderStatus::PendingInvoice),
            Role::Courier => $query->where('courier_id', $user->id),
            default => $query->whereRaw('1 = 0'),
        };
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function billingAddress(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class, 'billing_address_id');
    }

    public function shippingAddress(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class, 'shipping_address_id');
    }

    /**
     * Agente de call-center que creó el pedido.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function invoicer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invoiced_by');
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'courier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function commissions(): HasManyThrough
    {
        return $this->hasManyThrough(Commission::class, OrderItem::class);
    }
}
