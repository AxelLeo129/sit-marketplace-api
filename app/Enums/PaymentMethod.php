<?php

namespace App\Enums;

/**
 * Forma de pago de un pedido.
 */
enum PaymentMethod: string
{
    /** Pago con tarjeta; aplica la comisión del procesador de tarjetas. */
    case Card = 'card';

    /** Pago contra entrega. */
    case CashOnDelivery = 'cash_on_delivery';

    /**
     * Nombre legible de la forma de pago.
     */
    public function label(): string
    {
        return match ($this) {
            self::Card => 'Tarjeta',
            self::CashOnDelivery => 'Pago contra entrega',
        };
    }
}
