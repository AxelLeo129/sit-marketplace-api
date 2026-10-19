<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Services\SalesReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Filtros comunes de los reportes. Sin fechas, el período es el mes en curso.
 */
class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El permiso lo valida la ruta.
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'group_by' => ['nullable', Rule::in(['day', 'month'])],
            'role' => ['nullable', Rule::enum(Role::class)],
            'user_id' => ['nullable', 'integer'],
            'per_page' => ['integer', 'min:1', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return [
            'from' => 'desde',
            'to' => 'hasta',
            'group_by' => 'agrupar por',
            'role' => 'rol',
            'user_id' => 'usuario',
        ];
    }

    /**
     * Reporte del período pedido, de las 00:00 de `from` a las 23:59 de `to` (hora de Guatemala).
     */
    public function report(): SalesReport
    {
        [$from, $to] = $this->range();

        return new SalesReport($from->startOfDay(), $to->endOfDay());
    }

    /**
     * Período aplicado, para mostrarlo junto al reporte.
     *
     * @return array{from: string, to: string}
     */
    public function period(): array
    {
        [$from, $to] = $this->range();

        return ['from' => $from->toDateString(), 'to' => $to->toDateString()];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function range(): array
    {
        return [$this->date('from') ?? now()->startOfMonth(), $this->date('to') ?? now()];
    }
}
