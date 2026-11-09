<?php

namespace App\Providers;

use App\Repositories\Contracts\GastoOperativoRepositoryInterface;
use App\Repositories\Contracts\PresupuestoRepositoryInterface;
use App\Repositories\Eloquent\EloquentGastoOperativoRepository;
use App\Repositories\Eloquent\EloquentPresupuestoRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(GastoOperativoRepositoryInterface::class, EloquentGastoOperativoRepository::class);
        $this->app->bind(PresupuestoRepositoryInterface::class, EloquentPresupuestoRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
