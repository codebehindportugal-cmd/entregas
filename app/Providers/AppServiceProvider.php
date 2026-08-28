<?php

namespace App\Providers;

use App\Support\MoloniSettings;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Definicoes do Moloni guardadas na app (Gestao -> Definicoes Moloni)
        // por cima do config/moloni.php. Silencioso se a BD nao estiver pronta.
        MoloniSettings::aplicar();
    }
}
