<?php

namespace App\Enums;

/**
 * Eventos de autenticación que se registran para auditoría.
 */
enum AuthEventType: string
{
    case LoginSucceeded = 'login_succeeded';
    case LoginFailed = 'login_failed';
    case AccountLocked = 'account_locked';
    case Logout = 'logout';
    case PasswordChanged = 'password_changed';
    case PasswordResetRequested = 'password_reset_requested';
    case PasswordResetCompleted = 'password_reset_completed';

    /**
     * Nombre legible del evento.
     */
    public function label(): string
    {
        return match ($this) {
            self::LoginSucceeded => 'Inicio de sesión',
            self::LoginFailed => 'Intento fallido',
            self::AccountLocked => 'Cuenta bloqueada',
            self::Logout => 'Cierre de sesión',
            self::PasswordChanged => 'Cambio de contraseña',
            self::PasswordResetRequested => 'Solicitud de recuperación',
            self::PasswordResetCompleted => 'Contraseña restablecida',
        };
    }

    /**
     * Indica si el evento es una señal de alerta (para resaltarlo en pantalla).
     */
    public function isWarning(): bool
    {
        return $this === self::LoginFailed || $this === self::AccountLocked;
    }
}
