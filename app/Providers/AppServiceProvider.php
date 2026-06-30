<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

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
        View::composer('*', function ($view) {
            if (session()->has('fecha_inicio')) {
                $view->with('fechaInicio', session('fecha_inicio'));
            }
            if (session()->has('fecha_fin')) {
                $view->with('fechaFin', session('fecha_fin'));
            }
        });
    }
}
