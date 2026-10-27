<?php

namespace App\Services;

use App\Enums\AuthEventType;
use App\Models\AuthEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Registra eventos de autenticación (quién, cuándo, desde qué IP y navegador).
 *
 * Es "best effort": si falla el guardado, se anota en el log y el login sigue;
 * la auditoría nunca debe impedir que alguien entre o salga.
 */
class AuthAuditLog
{
    public function __construct(private readonly Request $request) {}

    public function record(AuthEventType $type, ?User $user = null, ?string $login = null, ?string $detail = null): void
    {
        try {
            AuthEvent::create([
                'type' => $type,
                'user_id' => $user?->id,
                'login' => $login !== null ? Str::limit($login, 250, '') : $user?->email,
                // La IP real cuando hay proxies de confianza configurados (TRUSTED_PROXIES).
                'ip_address' => $this->request->ip(),
                'user_agent' => Str::limit((string) $this->request->userAgent(), 250, ''),
                'detail' => $detail,
            ]);
        } catch (Throwable $e) {
            Log::warning("No se pudo registrar el evento de autenticación {$type->value}: {$e->getMessage()}");
        }
    }
}
