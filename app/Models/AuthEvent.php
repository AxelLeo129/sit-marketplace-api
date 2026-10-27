<?php

namespace App\Models;

use App\Enums\AuthEventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Evento de autenticación (inicio de sesión, intento fallido, bloqueo, etc.).
 * Solo se crean, nunca se editan; los más antiguos que la retención se borran a diario.
 */
#[Fillable(['type', 'user_id', 'login', 'ip_address', 'user_agent', 'detail'])]
class AuthEvent extends Model
{
    use Prunable;

    // Solo hay fecha de creación.
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type' => AuthEventType::class,
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * Eventos más antiguos que la retención configurada (los borra `model:prune`).
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(config('security.auth_events_retention_days')));
    }
}
