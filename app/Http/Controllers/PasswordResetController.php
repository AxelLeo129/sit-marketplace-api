<?php

namespace App\Http\Controllers;

use App\Enums\AuthEventType;
use App\Models\User;
use App\Services\AccountLockout;
use App\Services\AuthAuditLog;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * Recuperación de contraseña por correo.
 *
 * El sistema anterior generaba una contraseña de 6 caracteres y la enviaba
 * en texto plano por correo. Ahora se envía un enlace de un solo uso que
 * expira, y el usuario elige su nueva contraseña.
 */
#[Group('Autenticación', weight: 1)]
class PasswordResetController extends Controller
{
    public function __construct(
        private readonly AccountLockout $lockout,
        private readonly AuthAuditLog $audit,
    ) {}

    /**
     * Envía el enlace de recuperación. Responde lo mismo exista o no el correo,
     * para no revelar qué cuentas existen.
     */
    public function sendLink(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink($request->only('email'));
        $this->audit->record(
            AuthEventType::PasswordResetRequested,
            User::firstWhere('email', $request->input('email')),
            $request->input('email'),
        );

        return response()->json([
            'message' => 'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña.',
        ]);
    }

    /**
     * Cambia la contraseña con el token del enlace, cierra todas las sesiones
     * del usuario y quita el bloqueo por intentos fallidos.
     */
    public function reset(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->update(['password' => $password]);
                $user->tokens()->delete();
                $this->lockout->clear($user);
                $this->audit->record(AuthEventType::PasswordResetCompleted, $user);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['message' => 'El enlace no es válido o ya expiró.'], 422);
        }

        return response()->json(['message' => 'Contraseña restablecida. Ya puedes iniciar sesión.']);
    }
}
