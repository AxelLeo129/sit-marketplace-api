<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Commission;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Reportes de ventas y comisiones.
 *
 * Solo cuentan los pedidos entregados, por su fecha de entrega: un pedido
 * cancelado o pendiente no es venta ni genera comisión cobrable.
 * Todas las consultas usan parámetros enlazados; nunca SQL armado con datos del usuario.
 */
class SalesReport
{
    public function __construct(
        private readonly CarbonInterface $from,
        private readonly CarbonInterface $to,
    ) {}

    /**
     * Totales del período: ventas, impuestos, costos, margen, comisiones y utilidad.
     *
     * @return array<string, int|string>
     */
    public function summary(): array
    {
        $items = $this->deliveredItems()
            ->selectRaw('COUNT(DISTINCT orders.id) as orders_count')
            ->selectRaw('COALESCE(SUM(order_items.quantity), 0) as units')
            ->selectRaw('COALESCE(SUM(order_items.line_total), 0) as sales')
            ->selectRaw('COALESCE(SUM(order_items.net_amount), 0) as net')
            ->selectRaw('COALESCE(SUM(order_items.vat_amount), 0) as vat')
            ->selectRaw('COALESCE(SUM(order_items.card_fee_amount), 0) as card_fees')
            ->selectRaw('COALESCE(SUM(order_items.unit_cost * order_items.quantity), 0) as cost')
            ->selectRaw('COALESCE(SUM(order_items.gross_margin), 0) as gross_margin')
            ->toBase()
            ->first();

        $commissions = (float) $this->deliveredCommissions()->sum('commissions.amount');

        return [
            'orders_count' => (int) $items->orders_count,
            'units' => (int) $items->units,
            'sales' => $this->money($items->sales),
            'net' => $this->money($items->net),
            'vat' => $this->money($items->vat),
            'card_fees' => $this->money($items->card_fees),
            'cost' => $this->money($items->cost),
            'gross_margin' => $this->money($items->gross_margin),
            'commissions' => $this->money($commissions),
            // Lo que le queda al negocio después de pagar comisiones.
            'profit' => $this->money((float) $items->gross_margin - $commissions),
        ];
    }

    /**
     * Ventas y margen por día o por mes, para gráficas.
     *
     * @param  'day'|'month'  $groupBy
     * @return Collection<int, array{period: string, orders_count: int, sales: string, gross_margin: string}>
     */
    public function series(string $groupBy): Collection
    {
        // SUBSTR sobre "YYYY-MM-DD HH:MM:SS" funciona igual en MySQL y SQLite (tests).
        $period = $groupBy === 'month' ? 'SUBSTR(orders.delivered_at, 1, 7)' : 'SUBSTR(orders.delivered_at, 1, 10)';

        return $this->deliveredItems()
            ->selectRaw("{$period} as period")
            ->selectRaw('COUNT(DISTINCT orders.id) as orders_count')
            ->selectRaw('SUM(order_items.line_total) as sales')
            ->selectRaw('SUM(order_items.gross_margin) as gross_margin')
            ->groupByRaw($period)
            ->orderBy('period')
            ->toBase()
            ->get()
            ->map(fn (object $row) => [
                'period' => $row->period,
                'orders_count' => (int) $row->orders_count,
                'sales' => $this->money($row->sales),
                'gross_margin' => $this->money($row->gross_margin),
            ]);
    }

    /**
     * Productos más vendidos del período, por monto.
     *
     * @return Collection<int, array{product_id: int, name: string, units: int, sales: string, gross_margin: string}>
     */
    public function topProducts(int $limit = 10): Collection
    {
        return $this->deliveredItems()
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->select('products.id', 'products.name')
            ->selectRaw('SUM(order_items.quantity) as units')
            ->selectRaw('SUM(order_items.line_total) as sales')
            ->selectRaw('SUM(order_items.gross_margin) as gross_margin')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('sales')
            ->limit($limit)
            ->toBase()
            ->get()
            ->map(fn (object $row) => [
                'product_id' => (int) $row->id,
                'name' => $row->name,
                'units' => (int) $row->units,
                'sales' => $this->money($row->sales),
                'gross_margin' => $this->money($row->gross_margin),
            ]);
    }

    /**
     * Pedidos creados en el período agrupados por estado (incluye los no entregados),
     * para ver en qué paso del flujo están.
     *
     * @return array<string, int>
     */
    public function ordersByStatus(): array
    {
        $counts = Order::query()
            ->whereBetween('created_at', [$this->from, $this->to])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->toBase()
            ->pluck('total', 'status');

        return collect(OrderStatus::cases())
            ->mapWithKeys(fn (OrderStatus $status) => [$status->value => (int) ($counts[$status->value] ?? 0)])
            ->all();
    }

    /**
     * Comisiones del período agrupadas por beneficiario.
     *
     * @return Collection<int, array{user_id: int, name: string, role: string, role_label: string, orders_count: int, amount: string}>
     */
    public function commissionsByBeneficiary(?Role $role = null, ?int $userId = null): Collection
    {
        return $this->deliveredCommissions()
            ->join('users', 'users.id', '=', 'commissions.user_id')
            ->when($role, fn (Builder $query) => $query->where('commissions.role', $role))
            ->when($userId, fn (Builder $query) => $query->where('commissions.user_id', $userId))
            ->select('users.id', 'users.name', 'commissions.role')
            ->selectRaw('COUNT(DISTINCT orders.id) as orders_count')
            ->selectRaw('SUM(commissions.amount) as amount')
            ->groupBy('users.id', 'users.name', 'commissions.role')
            ->orderByDesc('amount')
            ->toBase()
            ->get()
            ->map(fn (object $row) => [
                'user_id' => (int) $row->id,
                'name' => $row->name,
                'role' => $row->role,
                'role_label' => Role::from($row->role)->label(),
                'orders_count' => (int) $row->orders_count,
                'amount' => $this->money($row->amount),
            ]);
    }

    /**
     * Comisiones de un beneficiario en el período, una por línea vendida, de la más reciente a la más antigua.
     *
     * @return Builder<Commission>
     */
    public function commissionsOf(int $userId): Builder
    {
        return $this->deliveredCommissions()
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('commissions.user_id', $userId)
            ->select('commissions.*', 'orders.id as order_id', 'orders.delivered_at', 'order_items.quantity', 'products.name as product_name')
            ->orderByDesc('orders.delivered_at')
            ->orderByDesc('commissions.id');
    }

    /**
     * Líneas de pedidos entregados en el período.
     *
     * @return Builder<OrderItem>
     */
    private function deliveredItems(): Builder
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', OrderStatus::Delivered)
            ->whereBetween('orders.delivered_at', [$this->from, $this->to]);
    }

    /**
     * Comisiones de líneas de pedidos entregados en el período.
     *
     * @return Builder<Commission>
     */
    private function deliveredCommissions(): Builder
    {
        return Commission::query()
            ->join('order_items', 'order_items.id', '=', 'commissions.order_item_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', OrderStatus::Delivered)
            ->whereBetween('orders.delivered_at', [$this->from, $this->to]);
    }

    /**
     * Monto con dos decimales, como texto, igual que el resto de la API.
     */
    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
