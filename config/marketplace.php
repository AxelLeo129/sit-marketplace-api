<?php

/*
 * Reglas de negocio de SIT Marketplace.
 *
 * Todas las tasas son porcentajes (12 = 12 %). Se copian a cada pedido al
 * crearlo, así que cambiarlas aquí no altera pedidos anteriores.
 */
return [

    // Administrador inicial que crea AdminUserSeeder.
    'admin' => [
        'name' => env('ADMIN_NAME', 'Administrador'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    // IVA incluido en el precio de venta.
    'vat_rate' => (float) env('MARKETPLACE_VAT_RATE', 12),

    // Comisión del procesador de tarjetas, sobre el ingreso neto. Solo aplica a pagos con tarjeta.
    'card_fee_rate' => (float) env('MARKETPLACE_CARD_FEE_RATE', 2.5),

    // Comisiones sobre el margen bruto de cada línea del pedido.
    'commission_rates' => [

        'sales_rep' => 2.0,

        'courier' => 0.5,

        // El médico gana menos con cada recompra del mismo producto por el
        // mismo cliente. La clave es el número de compra; de ahí en adelante
        // se usa el último valor de la lista.
        'doctor_by_purchase' => [
            1 => 20.0,
            2 => 15.0,
            3 => 10.0,
            4 => 5.0,
            5 => 0.0,
        ],

        // El call-center no gana en la primera compra (la generó el médico),
        // pero sí en las recompras que gestiona.
        'call_center_first_purchase' => 0.0,
        'call_center_repeat_purchase' => 1.0,
    ],

];
