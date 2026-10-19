<?php

namespace App\Http\Requests;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Facturación registra la factura de un pedido.
 */
class InvoiceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('invoice', $this->route('order'));
    }

    public function rules(): array
    {
        /** @var Order|null $order */
        $order = $this->route('order');

        return [
            'invoice_number' => ['required', 'string', 'max:50', Rule::unique('orders', 'invoice_number')],
            // Opcional: si no se envía, se factura con el NIT del cliente.
            'invoice_tax_id' => ['nullable', 'string', 'max:20', 'regex:'.Customer::TAX_ID_PATTERN],
            // Opcional: por defecto, ahora. No puede ser futura ni anterior al pedido.
            'invoiced_at' => array_filter([
                'nullable', 'date', 'before_or_equal:now',
                $order ? 'after_or_equal:'.$order->created_at->toDateString() : null,
            ]),
        ];
    }

    public function attributes(): array
    {
        return [
            'invoice_number' => 'número de factura',
            'invoice_tax_id' => 'NIT',
            'invoiced_at' => 'fecha de factura',
        ];
    }

    /**
     * Normaliza el NIT a mayúsculas y sin espacios ("cf" → "CF").
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('invoice_tax_id'))) {
            $this->merge(['invoice_tax_id' => strtoupper(str_replace(' ', '', $this->input('invoice_tax_id')))]);
        }
    }
}
