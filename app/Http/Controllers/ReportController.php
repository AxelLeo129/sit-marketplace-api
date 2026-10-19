<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\ReportRequest;
use App\Http\Resources\CommissionLineResource;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Reportes de ventas y comisiones (App\Services\SalesReport).
 *
 * Todos aceptan `from` y `to` (fechas); sin ellas, el período es el mes en curso.
 * Solo cuentan los pedidos entregados, por su fecha de entrega.
 */
#[Group('Reportes', weight: 8)]
class ReportController extends Controller
{
    /**
     * Resumen de ventas para el administrador: totales, serie por día o mes
     * (`group_by`), productos más vendidos y pedidos por estado.
     */
    public function sales(ReportRequest $request): JsonResponse
    {
        $report = $request->report();

        return response()->json([
            'period' => $request->period(),
            'summary' => $report->summary(),
            'series' => $report->series($request->validated('group_by', 'day')),
            'top_products' => $report->topProducts(),
            'orders_by_status' => $report->ordersByStatus(),
        ]);
    }

    /**
     * Comisiones por beneficiario para el administrador. Filtros opcionales: `role` y `user_id`.
     */
    public function commissions(ReportRequest $request): JsonResponse
    {
        $role = $request->validated('role');

        $rows = $request->report()->commissionsByBeneficiary(
            $role ? Role::from($role) : null,
            $request->integer('user_id') ?: null,
        );

        return response()->json([
            'period' => $request->period(),
            'total' => number_format((float) $rows->sum('amount'), 2, '.', ''),
            'data' => $rows,
        ]);
    }

    /**
     * Comisiones del usuario autenticado (médico, visitador, call-center o mensajero):
     * total del período y detalle paginado por línea vendida.
     */
    public function myCommissions(ReportRequest $request): AnonymousResourceCollection
    {
        $lines = $request->report()->commissionsOf($request->user()->id);
        $total = (float) (clone $lines)->reorder()->sum('commissions.amount');

        return CommissionLineResource::collection($lines->paginate($request->validated('per_page', 20)))
            ->additional([
                'period' => $request->period(),
                'total' => number_format($total, 2, '.', ''),
            ]);
    }
}
