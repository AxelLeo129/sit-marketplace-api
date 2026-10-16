<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use App\Services\SaleCalculator;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Se resuelve en cada uso para tomar siempre las tasas vigentes de la configuración.
        $this->app->bind(SaleCalculator::class, fn () => SaleCalculator::fromConfig());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Solo el administrador modifica el catálogo; el resto de roles solo lo consulta.
        Gate::define('manage-catalog', fn (User $user) => $user->hasRole(Role::Admin));

        // Reglas para toda contraseña nueva.
        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        // Un token deja de servir en cuanto el usuario se desactiva, sin esperar a que expire.
        Sanctum::authenticateAccessTokensUsing(
            fn ($accessToken, bool $isValid) => $isValid && $accessToken->tokenable?->is_active,
        );

        // El enlace del correo de recuperación abre la pantalla del frontend.
        ResetPassword::createUrlUsing(fn (User $user, string $token) => config('app.frontend_url')
            .'/reset-password?'.http_build_query(['token' => $token, 'email' => $user->email]));
    }
}
