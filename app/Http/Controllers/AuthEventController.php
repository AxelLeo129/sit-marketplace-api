<?php

namespace App\Http\Controllers;

use App\Enums\AuthEventType;
use App\Http\Resources\AuthEventResource;
use App\Models\AuthEvent;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Registro de accesos (solo administrador): inicios de sesión, intentos fallidos,
 * bloqueos y cambios de contraseña, con IP y navegador.
 */
#[Group('Seguridad', weight: 9)]
class AuthEventController extends Controller
{
    /**
     * Eventos del más reciente al más antiguo.
     *
     * Filtros: `type`, `search` (correo o colegiado escrito, o nombre del usuario),
     * `ip`, `from`, `to` y `per_page`.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'type' => ['nullable', Rule::enum(AuthEventType::class)],
            'search' => ['nullable', 'string', 'max:100'],
            'ip' => ['nullable', 'string', 'max:45'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['integer', 'min:1', 'max:100'],
        ]);

        $events = AuthEvent::query()
            ->with('user')
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($query) => $query
                ->where('login', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($query) => $query->where('name', 'like', "%{$search}%"))))
            ->when($filters['ip'] ?? null, fn ($query, $ip) => $query->where('ip_address', $ip))
            ->when($filters['from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->latest('id')
            ->paginate($filters['per_page'] ?? 30);

        return AuthEventResource::collection($events);
    }
}
