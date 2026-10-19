<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Order;
use App\Models\Prescription;
use App\Models\User;

/**
 * Quién puede ver, crear y cancelar pedidos.
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
}
