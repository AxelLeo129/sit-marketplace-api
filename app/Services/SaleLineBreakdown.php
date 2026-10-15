<?php

namespace App\Services;

use App\Enums\Role;

/**
 * Resultado del cálculo financiero de una línea de pedido.
 *
 * Los montos ya vienen redondeados a 2 decimales, listos para guardarse.
 */
final readonly class SaleLineBreakdown
{
    /**
     * @param  array<string, array{rate: float, amount: float}>  $commissions  Comisión por rol, indexada por el valor de App\Enums\Role.
     */
    public function __construct(
        public float $lineTotal,
        public float $netAmount,
        public float $vatAmount,
        public float $cardFeeAmount,
        public float $cost,
        public float $grossMargin,
        public array $commissions,
    ) {}

    /**
     * Comisión calculada para un rol.
     *
     * @return array{rate: float, amount: float}
     */
    public function commissionFor(Role $role): array
    {
        return $this->commissions[$role->value];
    }

    /**
     * Suma de todas las comisiones de la línea.
     */
    public function totalCommissions(): float
    {
        return round(array_sum(array_column($this->commissions, 'amount')), 2);
    }

    /**
     * Margen que queda después de pagar comisiones.
     */
    public function marginAfterCommissions(): float
    {
        return round($this->grossMargin - $this->totalCommissions(), 2);
    }
}
