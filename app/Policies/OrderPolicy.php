<?php

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Order;
use App\Models\Prescription;
use App\Models\User;

/**
 * Quién puede ver, crear, cancelar y mover los pedidos por el flujo
 * facturación → despacho → entrega.
 * La visibilidad por rol está en el scope Order::visibleTo().
 */
class OrderPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasRole(Role::Admin, Role::CallCenter, Role::Billing, Role::Dispatcher, Role::Courier);
    }

    public function view(User $actor, Order $order): bool
    {
        return Order::query()->visibleTo($actor)->whereKey($order->id)->exists();
    }

    /**
     * Crear un pedido a partir de una receta abierta que el call-center puede atender.
     */
    public function create(User $actor, Prescription $prescription): bool
    {
        return $actor->can('handleCall', $prescription);
    }

    public function cancel(User $actor, Order $order): bool
    {
        return $actor->hasRole(Role::Admin, Role::CallCenter) && $order->status->isCancellable();
    }

    /**
     * Facturación registra la factura de un pedido nuevo.
     */
    public function invoice(User $actor, Order $order): bool
    {
        return $actor->hasRole(Role::Admin, Role::Billing) && $order->status === OrderStatus::PendingInvoice;
    }

    /**
     * El despachador asigna o reasigna el mensajero.
     */
    public function assign(User $actor, Order $order): bool
    {
        return $actor->hasRole(Role::Admin, Role::Dispatcher) && $order->status->isAssignable();
    }

    /**
     * El mensajero asignado confirma que sale a ruta.
     */
    public function startDelivery(User $actor, Order $order): bool
    {
        return $this->isAssignedCourier($actor, $order) && $order->status === OrderStatus::Assigned;
    }

    /**
     * El mensajero asignado marca el pedido como entregado o como no entregado.
     */
    public function completeDelivery(User $actor, Order $order): bool
    {
        return $this->isAssignedCourier($actor, $order) && $order->status === OrderStatus::InTransit;
    }

    private function isAssignedCourier(User $actor, Order $order): bool
    {
        return $actor->hasRole(Role::Courier) && $order->courier_id === $actor->id;
    }
}
