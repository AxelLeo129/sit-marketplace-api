<?php

namespace App\Providers;

use App\Enums\Role;
use App\Filesystem\FirebaseStorageAdapter;
use App\Models\User;
use App\Services\SaleCalculator;
use Google\Cloud\Storage\StorageClient;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\Sanctum;
use League\Flysystem\Filesystem;
use League\Flysystem\GoogleCloudStorage\GoogleCloudStorageAdapter;
use League\Flysystem\GoogleCloudStorage\UniformBucketLevelAccessVisibility;

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

        // El call-center registra a los clientes y sus direcciones al crear pedidos.
        Gate::define('manage-customers', fn (User $user) => $user->hasRole(Role::Admin, Role::CallCenter));

        // El despachador reparte los pedidos facturados entre los mensajeros.
        Gate::define('dispatch-orders', fn (User $user) => $user->hasRole(Role::Admin, Role::Dispatcher));

        // Roles que cobran comisión y pueden consultar las suyas.
        Gate::define('earn-commissions', fn (User $user) => $user->hasRole(Role::Doctor, Role::SalesRep, Role::CallCenter, Role::Courier));

        // Costos, márgenes y comisiones: información sensible del negocio, solo para el administrador.
        Gate::define('view-financials', fn (User $user) => $user->hasRole(Role::Admin));

        // Reglas para toda contraseña nueva.
        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        // Un token deja de servir en cuanto el usuario se desactiva, sin esperar a que expire.
        Sanctum::authenticateAccessTokensUsing(
            fn ($accessToken, bool $isValid) => $isValid && $accessToken->tokenable?->is_active,
        );

        // El enlace del correo de recuperación abre la pantalla del frontend.
        ResetPassword::createUrlUsing(fn (User $user, string $token) => config('app.frontend_url')
            .'/reset-password?'.http_build_query(['token' => $token, 'email' => $user->email]));

        $this->registerFirebaseDisk();
    }

    /**
     * Driver "firebase" para Storage: el bucket de Cloud Storage for Firebase.
     * El cliente solo se crea cuando se usa el disco (MEDIA_DISK=firebase).
     */
    private function registerFirebaseDisk(): void
    {
        Storage::extend('firebase', function (Application $app, array $config) {
            $bucket = (new StorageClient([
                'projectId' => $config['project_id'],
                'keyFilePath' => $config['credentials'],
            ]))->bucket($config['bucket']);

            // Firebase controla el acceso con sus reglas, no con permisos por archivo (ACL).
            $adapter = new GoogleCloudStorageAdapter($bucket, visibilityHandler: new UniformBucketLevelAccessVisibility);

            return new FirebaseStorageAdapter(new Filesystem($adapter, $config), $adapter, $config);
        });
    }
}
