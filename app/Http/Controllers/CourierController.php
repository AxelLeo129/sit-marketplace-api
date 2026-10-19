<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Http\Resources\CourierResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Mensajeros disponibles para el despachador.
 */
#[Group('Pedidos', weight: 7)]
class CourierController extends Controller
{
    /**
     * Mensajeros activos con la cantidad de pedidos que tienen pendientes de entregar,
     * para repartir la carga al asignar.
     */
    public function index(): AnonymousResourceCollection
    {
        $couriers = User::query()
            ->where('role', Role::Courier)
            ->where('is_active', true)
            ->withCount(['deliveries as pending_deliveries_count' => fn ($query) => $query
                ->whereIn('status', [OrderStatus::Assigned, OrderStatus::InTransit])])
            ->orderBy('name')
            ->get();

        return CourierResource::collection($couriers);
    }
}
