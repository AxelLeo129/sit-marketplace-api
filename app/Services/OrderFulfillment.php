<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Commission;
use App\Models\Order;
use App\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Mueve un pedido por el flujo facturación → despacho → entrega.
 *
 * Cada paso bloquea el pedido y vuelve a validar el permiso con el estado
 * actual: si dos usuarios actúan sobre el mismo pedido a la vez, el segundo
 * recibe 403 en lugar de pisar el cambio del primero.
 */
class OrderFulfillment
{
    /**
     * Registra la factura. Si no se indica NIT, se usa el del cliente.
     *
     * @param  array{invoice_number: string, invoice_tax_id?: ?string, invoiced_at?: ?string}  $data  Datos ya validados.
     */
    public function invoice(User $actor, Order $order, array $data): Order
    {
        return $this->transition($actor, 'invoice', $order, fn (Order $order) => $order->update([
            'status' => OrderStatus::Invoiced,
            'invoice_tax_id' => $data['invoice_tax_id'] ?? $order->customer->tax_id,
            'invoice_number' => $data['invoice_number'],
            'invoiced_at' => $data['invoiced_at'] ?? now(),
            'invoiced_by' => $actor->id,
        ]));
    }

    /**
     * Asigna (o reasigna) el mensajero. La comisión de mensajero pasa a quien
     * tenga el pedido asignado, que es quien lo entregará.
     */
    public function assign(User $actor, Order $order, User $courier): Order
    {
        return $this->transition($actor, 'assign', $order, function (Order $order) use ($courier) {
            $order->update([
                'status' => OrderStatus::Assigned,
                'courier_id' => $courier->id,
                'assigned_at' => now(),
                'dispatched_at' => null,
            ]);

            Commission::query()
                ->whereIn('order_item_id', $order->items()->select('id'))
                ->where('role', Role::Courier)
                ->update(['user_id' => $courier->id]);
        });
    }

    /**
     * El mensajero sale a ruta con el pedido.
     */
    public function startDelivery(User $courier, Order $order): Order
    {
        return $this->transition($courier, 'startDelivery', $order, fn (Order $order) => $order->update([
            'status' => OrderStatus::InTransit,
            'dispatched_at' => now(),
        ]));
    }

    /**
     * El mensajero entrega el pedido.
     *
     * @param  array{received_by: string, notes?: ?string}  $data  Datos ya validados.
     */
    public function deliver(User $courier, Order $order, array $data): Order
    {
        return $this->transition($courier, 'completeDelivery', $order, fn (Order $order) => $order->update([
            'status' => OrderStatus::Delivered,
            'delivered_at' => now(),
            'received_by' => $data['received_by'],
            'delivery_notes' => $data['notes'] ?? null,
        ]));
    }

    /**
     * El mensajero no pudo entregar. El despachador puede reasignarlo
     * o el call-center cancelarlo.
     */
    public function failDelivery(User $courier, Order $order, string $notes): Order
    {
        return $this->transition($courier, 'completeDelivery', $order, fn (Order $order) => $order->update([
            'status' => OrderStatus::DeliveryFailed,
            'delivery_notes' => $notes,
        ]));
    }

    /**
     * Bloquea el pedido, revalida el permiso con su estado actual y aplica el cambio.
     *
     * @param  Closure(Order): mixed  $change
     *
     * @throws AuthorizationException Si el pedido ya cambió de estado.
     */
    private function transition(User $actor, string $ability, Order $order, Closure $change): Order
    {
        return DB::transaction(function () use ($actor, $ability, $order, $change) {
            $order = Order::with('customer')->lockForUpdate()->findOrFail($order->id);

            Gate::forUser($actor)->authorize($ability, $order);
            $change($order);

            return $order;
        });
    }
}
