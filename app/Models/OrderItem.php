<?php

namespace App\Models;

use App\Enums\ItemOrigin;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Línea de un pedido con su desglose financiero.
 */
#[Fillable([
    'order_id', 'product_id', 'origin', 'quantity', 'unit_price', 'unit_cost',
    'line_total', 'net_amount', 'vat_amount', 'card_fee_amount', 'gross_margin', 'purchase_number',
])]
class OrderItem extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'origin' => ItemOrigin::class,
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'line_total' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'card_fee_amount' => 'decimal:2',
            'gross_margin' => 'decimal:2',
            'purchase_number' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }
}
