<?php

namespace App\Http\Controllers;

use App\Enums\AuthEventType;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AccountLockout;
use App\Services\AuthAuditLog;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Autenticación por token (Sanctum).
 *
 * No hay registro público: los usuarios los crea un administrador. En el
 * sistema anterior el registro aceptaba el rol desde la petición y cualquiera
 * podía crearse una cuenta de administrador.
 */
#[Group('Autenticación', weight: 1)]
class AuthController extends Controller
{
    public function __construct(
        private readonly AccountLockout $lockout,
        private readonly AuthAuditLog $audit,
    ) {}

    /**
     * Inicia sesión con correo o número de colegiado y devuelve un token.
     *
     * Tras 5 contraseñas incorrectas seguidas la cuenta se bloquea 15 minutos
     * (el doble en cada bloqueo siguiente) y responde 429.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $login = $request->validated('login');
        $password = $request->validated('password');
        $column = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'collegiate_number';

        $user = User::where($column, $login)->first();

        if ($user && ($lockedUntil = $this->lockout->lockedUntil($user))) {
            $this->audit->record(AuthEventType::LoginFailed, $user, $login, 'account_locked');

            return $this->lockedResponse($lockedUntil);
        }

        // Sin usuario se compara contra un hash de relleno: así la respuesta tarda lo mismo
        // y el tiempo no delata qué cuentas existen.
        $passwordMatches = Hash::check($password, $user?->password ?? $this->dummyHash());

        // Mismo mensaje en todos los casos para no revelar qué cuentas existen.
        if (! $user || ! $passwordMatches || ! $user->is_active) {
            $this->audit->record(AuthEventType::LoginFailed, $user, $login, match (true) {
                ! $user => 'unknown_user',
                ! $passwordMatches => 'bad_password',
                default => 'inactive_user',
            });

            if ($user && ! $passwordMatches && ($lockedUntil = $this->lockout->registerFailure($user))) {
                $this->audit->record(AuthEventType::AccountLocked, $user, $login, "until {$lockedUntil->toIso8601String()}");

                return $this->lockedResponse($lockedUntil);
            }

            return response()->json(['message' => 'Credenciales inválidas.'], 401);
        }

        $this->lockout->clear($user);

        // Si cambió el algoritmo (p. ej. de bcrypt a Argon2id), se vuelve a cifrar con el actual.
        if (Hash::needsRehash($user->password)) {
            $user->forceFill(['password' => $password])->save();
        }

        $this->audit->record(AuthEventType::LoginSucceeded, $user, $login);

        return response()->json([
            'user' => new UserResource($user),
            'token' => $user->createToken('auth-token')->plainTextToken,
        ]);
    }

    /**
     * Revoca el token usado en la petición.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        $this->audit->record(AuthEventType::Logout, $request->user());

        return response()->json(['message' => 'Sesión cerrada.']);
    }

    private function lockedResponse(Carbon $until): JsonResponse
    {
        $minutes = max(1, (int) ceil(now()->diffInSeconds($until) / 60));

        return response()->json(
            ['message' => "Demasiados intentos fallidos. La cuenta está bloqueada; intenta de nuevo en {$minutes} minutos o restablece tu contraseña."],
            429,
            ['Retry-After' => $minutes * 60],
        );
    }

    /**
     * Hash de relleno con el algoritmo actual, calculado una sola vez.
     */
    private function dummyHash(): string
    {
        return Cache::rememberForever('auth.dummy-hash.'.config('hashing.driver'), fn () => Hash::make(Str::random(40)));
    }
}
