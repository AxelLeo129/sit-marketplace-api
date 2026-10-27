<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Bloqueo de cuentas por contraseñas incorrectas seguidas.
 *
 * Tras `max_attempts` fallos la cuenta se bloquea `base_minutes`; cada bloqueo
 * siguiente sin un login correcto de por medio dura el doble (hasta `max_minutes`).
 * Un login correcto o un restablecimiento de contraseña lo limpia todo.
 *
 * Complementa el límite por IP: frena también los ataques repartidos entre muchas IPs.
 */
class AccountLockout
{
    /**
     * Fecha hasta la que la cuenta está bloqueada, o null si no lo está.
     */
    public function lockedUntil(User $user): ?Carbon
    {
        return $user->locked_until?->isFuture() ? $user->locked_until : null;
    }

    /**
     * Suma un intento fallido. Devuelve la fecha de desbloqueo si con este intento se bloqueó.
     */
    public function registerFailure(User $user): ?Carbon
    {
        $attempts = $user->failed_login_attempts + 1;

        if ($attempts < config('security.lockout.max_attempts')) {
            $user->forceFill(['failed_login_attempts' => $attempts])->save();

            return null;
        }

        $minutes = min(
            config('security.lockout.base_minutes') * 2 ** $user->lockout_count,
            config('security.lockout.max_minutes'),
        );
        $lockedUntil = now()->addMinutes($minutes);

        // El contador vuelve a 0 para que el siguiente ciclo cuente desde cero.
        $user->forceFill([
            'failed_login_attempts' => 0,
            'lockout_count' => min($user->lockout_count + 1, 255),
            'locked_until' => $lockedUntil,
        ])->save();

        return $lockedUntil;
    }

    /**
     * Limpia intentos y bloqueos (login correcto o contraseña restablecida).
     */
    public function clear(User $user): void
    {
        if ($user->failed_login_attempts || $user->lockout_count || $user->locked_until) {
            $user->forceFill(['failed_login_attempts' => 0, 'lockout_count' => 0, 'locked_until' => null])->save();
        }
    }
}
