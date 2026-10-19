<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Prescription;
use App\Services\OrderPlacement;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Pedidos. El call-center los crea a partir de una receta.
 * Los permisos están en App\Policies\OrderPolicy.
 */
#[Group('Pedidos', weight: 7)]
class OrderController extends Controller
{
    public function __construct(private readonly OrderPlacement $placement) {}

    /**
     * Lista paginada de los pedidos que el usuario puede ver, del más reciente al más antiguo.
     *
     * Filtros: `status`, `search` (número de pedido, nombre o teléfono del cliente),
     * `created_from`, `created_to` y `per_page`.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Order::class);

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'search' => ['nullable', 'string', 'max:100'],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date', 'after_or_equal:created_from'],
            'per_page' => ['integer', 'min:1', 'max:100'],
        ]);

        $orders = Order::query()
            ->visibleTo($request->user())
            ->with($this->relations())
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($query) => $query
                ->where('id', ctype_digit($search) ? (int) $search : 0)
                ->orWhereHas('customer', fn ($query) => $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%"))))
            ->when($filters['created_from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['created_to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->latest('id')
            ->paginate($filters['per_page'] ?? 20);

        return OrderResource::collection($orders);
    }

    /**
     * Convierte una receta en pedido. Reserva las existencias, calcula IVA,
     * margen y comisiones, y deja la receta como "Con pedido".
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $prescription = Prescription::findOrFail($request->validated('prescription_id'));
        $order = $this->placement->place($request->user(), $prescription, $request->validated());

        return (new OrderResource($order->load($this->relations())))->response()->setStatusCode(201);
    }

    public function show(Order $order): OrderResource
    {
        Gate::authorize('view', $order);

        return new OrderResource($order->load($this->relations()));
    }

    /**
     * Cancela un pedido aún no asignado a mensajero y devuelve las existencias.
     */
    public function cancel(Order $order): OrderResource
    {
        Gate::authorize('cancel', $order);

        return new OrderResource($this->placement->cancel($order)->load($this->relations()));
    }

    /**
     * Relaciones a cargar. Las comisiones solo si el usuario puede verlas.
     *
     * @return array<int, string>
     */
    private function relations(): array
    {
        return [
            'customer', 'billingAddress', 'shippingAddress', 'creator', 'courier', 'items.product.presentation',
            ...(Gate::allows('view-financials') ? ['items.commissions.beneficiary'] : []),
        ];
    }
}
