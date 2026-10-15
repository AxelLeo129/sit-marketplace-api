<?php

namespace App\Services;

use App\Enums\ItemOrigin;
use App\Enums\PaymentMethod;
use App\Enums\Role;

/**
 * Calcula IVA, comisión de tarjeta, margen y comisiones de una línea de pedido.
 *
 * En el sistema anterior este cálculo se hacía en el navegador y el servidor
 * guardaba lo que recibía, así que cualquiera podía alterar las comisiones.
 * Ahora solo el backend calcula, con las tasas de config/marketplace.php.
 *
 * Fórmula:
 *   total línea   = precio unitario × cantidad          (IVA incluido)
 *   ingreso neto  = total línea / (1 + IVA)
 *   IVA           = ingreso neto × IVA
 *   cargo tarjeta = ingreso neto × tasa tarjeta         (solo con tarjeta)
 *   margen bruto  = ingreso neto − cargo tarjeta − costo unitario × cantidad
 *   comisión      = margen bruto × tasa del rol
 *
 * Reglas:
 *   - Si el margen bruto es cero o negativo, nadie cobra comisión.
 *   - El médico solo cobra por los productos que él recetó, no por los que
 *     agregó el call-center durante la llamada.
 */
class SaleCalculator
{
    /**
     * @param  float  $vatRate  IVA en porcentaje.
     * @param  float  $cardFeeRate  Comisión de tarjeta en porcentaje.
     * @param  array<string, mixed>  $commissionRates  Estructura de `marketplace.commission_rates`.
     */
    public function __construct(
        private readonly float $vatRate,
        private readonly float $cardFeeRate,
        private readonly array $commissionRates,
    ) {}

    /**
     * Crea la calculadora con las tasas de la configuración actual.
     */
    public static function fromConfig(): self
    {
        return new self(
            config('marketplace.vat_rate'),
            config('marketplace.card_fee_rate'),
            config('marketplace.commission_rates'),
        );
    }

    public function vatRate(): float
    {
        return $this->vatRate;
    }

    /**
     * Tasa de tarjeta que corresponde a la forma de pago (0 si no es tarjeta).
     */
    public function cardFeeRateFor(PaymentMethod $paymentMethod): float
    {
        return $paymentMethod === PaymentMethod::Card ? $this->cardFeeRate : 0.0;
    }

    /**
     * Calcula el desglose de una línea.
     *
     * @param  int  $purchaseNumber  Qué número de compra es (1 = primera) de este
     *                               producto por este cliente con este médico.
     */
    public function calculateLine(
        float $unitPrice,
        float $unitCost,
        int $quantity,
        int $purchaseNumber,
        PaymentMethod $paymentMethod,
        ItemOrigin $origin,
    ): SaleLineBreakdown {
        $lineTotal = $unitPrice * $quantity;
        $netAmount = $lineTotal / (1 + $this->vatRate / 100);
        $vatAmount = $netAmount * $this->vatRate / 100;
        $cardFeeAmount = $netAmount * $this->cardFeeRateFor($paymentMethod) / 100;
        $cost = $unitCost * $quantity;
        $grossMargin = $netAmount - $cardFeeAmount - $cost;

        // Una venta con pérdida no genera comisiones negativas.
        $commissionBase = max($grossMargin, 0.0);

        $commissions = [];
        foreach ($this->commissionRatesFor($purchaseNumber, $origin) as $role => $rate) {
            $commissions[$role] = [
                'rate' => $rate,
                'amount' => round($commissionBase * $rate / 100, 2),
            ];
        }

        return new SaleLineBreakdown(
            lineTotal: round($lineTotal, 2),
            netAmount: round($netAmount, 2),
            vatAmount: round($vatAmount, 2),
            cardFeeAmount: round($cardFeeAmount, 2),
            cost: round($cost, 2),
            grossMargin: round($grossMargin, 2),
            commissions: $commissions,
        );
    }

    /**
     * Tasas de comisión por rol según el número de compra y el origen del producto.
     *
     * @return array<string, float>
     */
    public function commissionRatesFor(int $purchaseNumber, ItemOrigin $origin): array
    {
        $isFirstPurchase = $purchaseNumber <= 1;
        $doctorRate = $origin === ItemOrigin::Prescribed ? $this->doctorRateFor($purchaseNumber) : 0.0;

        return [
            Role::Doctor->value => $doctorRate,
            Role::SalesRep->value => (float) $this->commissionRates['sales_rep'],
            Role::CallCenter->value => (float) ($isFirstPurchase
                ? $this->commissionRates['call_center_first_purchase']
                : $this->commissionRates['call_center_repeat_purchase']),
            Role::Courier->value => (float) $this->commissionRates['courier'],
        ];
    }

    /**
     * Tasa del médico: baja con cada recompra y se queda en el último valor.
     */
    private function doctorRateFor(int $purchaseNumber): float
    {
        $rates = $this->commissionRates['doctor_by_purchase'];
        $lastPurchaseNumber = max(array_keys($rates));

        return (float) $rates[min(max($purchaseNumber, 1), $lastPurchaseNumber)];
    }
}
