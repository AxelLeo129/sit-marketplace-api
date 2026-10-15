<?php

namespace App\Models;

use App\Enums\AddressType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cliente que compra los medicamentos (normalmente el paciente de la receta).
 */
#[Fillable(['name', 'tax_id', 'phone', 'email', 'gender', 'birth_date'])]
class Customer extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
        ];
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function billingAddresses(): HasMany
    {
        return $this->addresses()->where('type', AddressType::Billing);
    }

    public function shippingAddresses(): HasMany
    {
        return $this->addresses()->where('type', AddressType::Shipping);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
