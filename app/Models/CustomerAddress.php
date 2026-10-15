<?php

namespace App\Models;

use App\Enums\AddressType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dirección de facturación o de entrega de un cliente.
 */
#[Fillable(['customer_id', 'type', 'country', 'department', 'municipality', 'street', 'apartment', 'neighborhood', 'postal_code', 'phone'])]
class CustomerAddress extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => AddressType::class,
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
