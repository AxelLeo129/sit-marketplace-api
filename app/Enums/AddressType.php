<?php

namespace App\Enums;

/**
 * Uso de una dirección del cliente.
 */
enum AddressType: string
{
    case Billing = 'billing';
    case Shipping = 'shipping';
}
