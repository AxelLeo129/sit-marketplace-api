<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Comisión que una línea de pedido genera para un beneficiario.
 */
#[Fillable(['order_item_id', 'user_id', 'role', 'rate', 'amount'])]
class Commission extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'rate' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
