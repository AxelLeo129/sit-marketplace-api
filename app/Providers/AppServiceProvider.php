<?php

namespace App\Providers;

use App\Services\SaleCalculator;
use Illuminate\Support\ServiceProvider;

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
        //
    }
}
