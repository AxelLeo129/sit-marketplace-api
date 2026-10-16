<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Autenticación por token (Sanctum).
 *
 * No hay registro público: los usuarios los crea un administrador. En el
 * sistema anterior el registro aceptaba el rol desde la petición y cualquiera
 * podía crearse una cuenta de administrador.
 */
class AuthController extends Controller
{
    /**
     * Inicia sesión con correo o número de colegiado y devuelve un token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $login = $request->validated('login');
        $column = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'collegiate_number';

        $user = User::where($column, $login)->first();

        // Mismo mensaje en todos los casos para no revelar qué cuentas existen.
        if (! $user || ! $user->is_active || ! Hash::check($request->validated('password'), $user->password)) {
            return response()->json(['message' => 'Credenciales inválidas.'], 401);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }

    /**
     * Revoca el token usado en la petición.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada.']);
    }
}
