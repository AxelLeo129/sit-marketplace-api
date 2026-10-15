<?php

namespace Tests\Unit;

use App\Enums\ItemOrigin;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Services\SaleCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SaleCalculatorTest extends TestCase
{
    /**
     * Calculadora con las mismas tasas que usaba el sistema anterior.
     */
    private function calculator(): SaleCalculator
    {
        return new SaleCalculator(
            vatRate: 12,
            cardFeeRate: 2.5,
            commissionRates: [
                'sales_rep' => 2.0,
                'courier' => 0.5,
                'doctor_by_purchase' => [1 => 20.0, 2 => 15.0, 3 => 10.0, 4 => 5.0, 5 => 0.0],
                'call_center_first_purchase' => 0.0,
                'call_center_repeat_purchase' => 1.0,
            ],
        );
    }

    public function test_breaks_down_a_cash_sale(): void
    {
        // Precio Q112 con IVA → neto Q100, IVA Q12; costo Q60 → margen Q40.
        $line = $this->calculator()->calculateLine(112, 60, 1, 1, PaymentMethod::CashOnDelivery, ItemOrigin::Prescribed);

        $this->assertSame(112.0, $line->lineTotal);
        $this->assertSame(100.0, $line->netAmount);
        $this->assertSame(12.0, $line->vatAmount);
        $this->assertSame(0.0, $line->cardFeeAmount);
        $this->assertSame(40.0, $line->grossMargin);
    }

    public function test_card_payment_deducts_the_card_fee_from_the_margin(): void
    {
        // 2.5 % de Q100 neto = Q2.50 → margen Q37.50.
        $line = $this->calculator()->calculateLine(112, 60, 1, 1, PaymentMethod::Card, ItemOrigin::Prescribed);

        $this->assertSame(2.5, $line->cardFeeAmount);
        $this->assertSame(37.5, $line->grossMargin);
    }

    public function test_multiplies_amounts_by_quantity(): void
    {
        // El sistema anterior calculaba comisiones por unidad aunque se vendieran varias.
        $line = $this->calculator()->calculateLine(112, 60, 3, 1, PaymentMethod::CashOnDelivery, ItemOrigin::Prescribed);

        $this->assertSame(336.0, $line->lineTotal);
        $this->assertSame(180.0, $line->cost);
        $this->assertSame(120.0, $line->grossMargin);
        $this->assertSame(24.0, $line->commissionFor(Role::Doctor)['amount']);
    }

    public function test_first_purchase_commissions_include_the_courier(): void
    {
        $line = $this->calculator()->calculateLine(112, 60, 1, 1, PaymentMethod::CashOnDelivery, ItemOrigin::Prescribed);

        $this->assertSame(['rate' => 20.0, 'amount' => 8.0], $line->commissionFor(Role::Doctor));
        $this->assertSame(['rate' => 2.0, 'amount' => 0.8], $line->commissionFor(Role::SalesRep));
        $this->assertSame(['rate' => 0.0, 'amount' => 0.0], $line->commissionFor(Role::CallCenter));
        $this->assertSame(['rate' => 0.5, 'amount' => 0.2], $line->commissionFor(Role::Courier));
        $this->assertSame(9.0, $line->totalCommissions());
        $this->assertSame(31.0, $line->marginAfterCommissions());
    }

    public function test_sale_below_cost_pays_no_commissions(): void
    {
        // Neto Q100, costo Q150 → margen −Q50.
        $line = $this->calculator()->calculateLine(112, 150, 1, 1, PaymentMethod::CashOnDelivery, ItemOrigin::Prescribed);

        $this->assertSame(-50.0, $line->grossMargin);
        $this->assertSame(0.0, $line->totalCommissions());
        $this->assertSame(-50.0, $line->marginAfterCommissions());
    }

    public function test_doctor_earns_nothing_on_products_added_by_call_center(): void
    {
        $line = $this->calculator()->calculateLine(112, 60, 1, 1, PaymentMethod::CashOnDelivery, ItemOrigin::AddedByCallCenter);

        $this->assertSame(['rate' => 0.0, 'amount' => 0.0], $line->commissionFor(Role::Doctor));
        $this->assertSame(0.8, $line->commissionFor(Role::SalesRep)['amount']);
    }

    /**
     * @return array<string, array{int, float, float}>
     */
    public static function purchaseNumbers(): array
    {
        return [
            'primera compra' => [1, 20.0, 0.0],
            'segunda compra' => [2, 15.0, 1.0],
            'tercera compra' => [3, 10.0, 1.0],
            'cuarta compra' => [4, 5.0, 1.0],
            'quinta compra' => [5, 0.0, 1.0],
            'después de la última tasa' => [12, 0.0, 1.0],
        ];
    }

    #[DataProvider('purchaseNumbers')]
    public function test_doctor_rate_decreases_with_each_repurchase(int $purchaseNumber, float $doctorRate, float $callCenterRate): void
    {
        $rates = $this->calculator()->commissionRatesFor($purchaseNumber, ItemOrigin::Prescribed);

        $this->assertSame($doctorRate, $rates[Role::Doctor->value]);
        $this->assertSame($callCenterRate, $rates[Role::CallCenter->value]);
    }
}
