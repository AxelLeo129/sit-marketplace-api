<?php

namespace App\Http\Requests;

use App\Enums\AddressType;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Models\Order;
use App\Models\Prescription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * El call-center convierte una receta en pedido.
 *
 * El origen de cada producto (recetado o agregado), los precios, el IVA y las
 * comisiones los calcula el servidor; el cliente solo envía productos y cantidades.
 */
class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $prescription = Prescription::find($this->input('prescription_id'));

        // Si la receta no existe, que responda la validación (422) y no un 403.
        return $prescription
            ? $this->user()->can('create', [Order::class, $prescription])
            : $this->user()->hasRole(Role::Admin, Role::CallCenter);
    }

    public function rules(): array
    {
        $customerAddress = fn (AddressType $type) => Rule::exists('customer_addresses', 'id')
            ->where('customer_id', $this->integer('customer_id'))
            ->where('type', $type->value);

        return [
            'prescription_id' => ['required', 'integer', 'exists:prescriptions,id'],
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            // Las direcciones deben ser de este cliente y del tipo correcto.
            'billing_address_id' => ['required', 'integer', $customerAddress(AddressType::Billing)],
            'shipping_address_id' => ['required', 'integer', $customerAddress(AddressType::Shipping)],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            // Datos de tarjeta: solo titular, últimos 4 dígitos y número de autorización. Nunca el número completo.
            'payment_reference' => ['required_if:payment_method,card', 'prohibited_unless:payment_method,card', 'nullable', 'string', 'max:50'],
            'cardholder_name' => ['required_if:payment_method,card', 'prohibited_unless:payment_method,card', 'nullable', 'string', 'max:255'],
            'card_last_four' => ['required_if:payment_method,card', 'prohibited_unless:payment_method,card', 'nullable', 'digits:4'],
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.product_id' => [
                'required', 'integer', 'distinct',
                Rule::exists('products', 'id')->where('is_active', true)->whereNull('deleted_at'),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ];
    }

    public function attributes(): array
    {
        return [
            'prescription_id' => 'receta',
            'customer_id' => 'cliente',
            'billing_address_id' => 'dirección de facturación',
            'shipping_address_id' => 'dirección de entrega',
            'payment_method' => 'forma de pago',
            'payment_reference' => 'número de autorización',
            'cardholder_name' => 'titular de la tarjeta',
            'card_last_four' => 'últimos 4 dígitos',
            'items' => 'productos',
            'items.*.product_id' => 'producto',
            'items.*.quantity' => 'cantidad',
        ];
    }
}
