<?php

namespace App\Services;

use App\Enums\ItemOrigin;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PrescriptionStatus;
use App\Enums\Role;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Crea y cancela pedidos.
 *
 * Todo ocurre dentro de una transacción con bloqueo de filas: si dos agentes
 * procesan la misma receta o compiten por el último producto en existencia,
 * solo uno lo consigue.
 */
class OrderPlacement
{
    public function __construct(private readonly SaleCalculator $calculator) {}

    /**
     * Convierte una receta en pedido: reserva existencias y calcula el
     * desglose financiero y las comisiones de cada línea.
     *
     * @param  array{customer_id: int, billing_address_id: int, shipping_address_id: int, payment_method: string,
     *     payment_reference?: ?string, cardholder_name?: ?string, card_last_four?: ?string,
     *     items: array<int, array{product_id: int, quantity: int}>}  $data  Datos ya validados.
     *
     * @throws ValidationException Si la receta ya no está abierta o no hay existencias.
     */
    public function place(User $agent, Prescription $prescription, array $data): Order
    {
        return DB::transaction(function () use ($agent, $prescription, $data) {
            $prescription = Prescription::with('doctor', 'items')->lockForUpdate()->findOrFail($prescription->id);

            if (! $prescription->status->isOpen()) {
                throw ValidationException::withMessages(['prescription_id' => 'La receta ya fue procesada.']);
            }

            $products = Product::whereKey(array_column($data['items'], 'product_id'))->lockForUpdate()->get()->keyBy('id');
            $this->ensureStock($data['items'], $products);

            $paymentMethod = PaymentMethod::from($data['payment_method']);
            $order = Order::create([
                'prescription_id' => $prescription->id,
                'customer_id' => $data['customer_id'],
                'billing_address_id' => $data['billing_address_id'],
                'shipping_address_id' => $data['shipping_address_id'],
                'created_by' => $agent->id,
                'status' => OrderStatus::PendingInvoice,
                'payment_method' => $paymentMethod,
                'payment_reference' => $data['payment_reference'] ?? null,
                'cardholder_name' => $data['cardholder_name'] ?? null,
                'card_last_four' => $data['card_last_four'] ?? null,
                'vat_rate' => $this->calculator->vatRate(),
                'card_fee_rate' => $this->calculator->cardFeeRateFor($paymentMethod),
                'total' => 0,
            ]);

            $prescribedProductIds = $prescription->items->pluck('product_id');
            $beneficiaries = [
                Role::Doctor->value => $prescription->doctor_id,
                Role::SalesRep->value => $prescription->doctor->sales_rep_id,
                Role::CallCenter->value => $agent->id,
                Role::Courier->value => null, // Se asigna cuando el despachador elige al mensajero.
            ];

            $total = 0.0;
            foreach ($data['items'] as $line) {
                $product = $products[$line['product_id']];
                $item = $this->addItem($order, $product, $line['quantity'], $paymentMethod, $prescription, $prescribedProductIds, $beneficiaries);
                $product->decrement('stock', $line['quantity']);
                $total += (float) $item->line_total;
            }

            $order->update(['total' => round($total, 2)]);
            $prescription->update(['status' => PrescriptionStatus::Ordered, 'callback_at' => null]);

            return $order;
        });
    }

    /**
     * Cancela el pedido, devuelve las existencias y cierra la receta.
     * Las comisiones se conservan para auditoría; los reportes excluyen pedidos cancelados.
     *
     * @throws ValidationException Si el pedido ya avanzó y no se puede cancelar.
     */
    public function cancel(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order = Order::with('items', 'prescription')->lockForUpdate()->findOrFail($order->id);

            if (! $order->status->isCancellable()) {
                throw ValidationException::withMessages(['status' => 'El pedido ya no se puede cancelar.']);
            }

            foreach ($order->items as $item) {
                Product::withTrashed()->whereKey($item->product_id)->increment('stock', $item->quantity);
            }

            $order->update(['status' => OrderStatus::Cancelled]);
            $order->prescription?->update(['status' => PrescriptionStatus::Cancelled]);

            return $order;
        });
    }

    /**
     * Rechaza el pedido si algún producto no tiene existencias suficientes.
     *
     * @param  array<int, array{product_id: int, quantity: int}>  $items
     * @param  Collection<int, Product>  $products
     */
    private function ensureStock(array $items, Collection $products): void
    {
        $errors = [];
        foreach ($items as $index => $line) {
            $product = $products[$line['product_id']];
            if ($product->stock < $line['quantity']) {
                $errors["items.{$index}.quantity"] = "Solo hay {$product->stock} unidades de {$product->name}.";
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Crea la línea del pedido y sus comisiones.
     *
     * @param  Collection<int, int>  $prescribedProductIds
     * @param  array<string, ?int>  $beneficiaries  Usuario que cobra cada comisión, por rol.
     */
    private function addItem(
        Order $order,
        Product $product,
        int $quantity,
        PaymentMethod $paymentMethod,
        Prescription $prescription,
        Collection $prescribedProductIds,
        array $beneficiaries,
    ): OrderItem {
        // El origen lo decide el servidor: recetado si estaba en la receta.
        $origin = $prescribedProductIds->contains($product->id) ? ItemOrigin::Prescribed : ItemOrigin::AddedByCallCenter;
        $purchaseNumber = $this->purchaseNumber($product->id, $order->customer_id, $prescription->doctor_id);

        $line = $this->calculator->calculateLine(
            (float) $product->price, (float) $product->cost, $quantity, $purchaseNumber, $paymentMethod, $origin,
        );

        $item = $order->items()->create([
            'product_id' => $product->id,
            'origin' => $origin,
            'quantity' => $quantity,
            'unit_price' => $product->price,
            'unit_cost' => $product->cost,
            'line_total' => $line->lineTotal,
            'net_amount' => $line->netAmount,
            'vat_amount' => $line->vatAmount,
            'card_fee_amount' => $line->cardFeeAmount,
            'gross_margin' => $line->grossMargin,
            'purchase_number' => $purchaseNumber,
        ]);

        foreach ($line->commissions as $role => $commission) {
            // Sin tasa no hay comisión, y sin visitador asignado no hay a quién pagarle la suya.
            if ($commission['rate'] <= 0 || ($role === Role::SalesRep->value && $beneficiaries[$role] === null)) {
                continue;
            }

            $item->commissions()->create([
                'user_id' => $beneficiaries[$role],
                'role' => $role,
                'rate' => $commission['rate'],
                'amount' => $commission['amount'],
            ]);
        }

        return $item;
    }

    /**
     * Número de compra (1 = primera) de este producto por este cliente con
     * recetas de este médico, sin contar pedidos cancelados.
     */
    private function purchaseNumber(int $productId, int $customerId, int $doctorId): int
    {
        $previous = OrderItem::query()
            ->where('product_id', $productId)
            ->whereHas('order', fn ($query) => $query
                ->where('customer_id', $customerId)
                ->whereNot('status', OrderStatus::Cancelled)
                ->whereHas('prescription', fn ($query) => $query->where('doctor_id', $doctorId)))
            ->count();

        return $previous + 1;
    }
}
