<?php

namespace App\Modules\GestionPersonal\Infrastructure\Providers;

use App\Modules\GestionPersonal\Application\Ports\AccesosDeCuentaPort;
use App\Modules\GestionPersonal\Application\Ports\ContextoDeTenantPort;
use App\Modules\GestionPersonal\Application\Ports\LectorDeCapacidadesPort;
use App\Modules\GestionPersonal\Application\Ports\LectorDeDesarrolloPort;
use App\Modules\GestionPersonal\Application\Ports\LectorEstructuraPort;
use App\Modules\GestionPersonal\Application\Ports\LectorLaboralPort;
use App\Modules\GestionPersonal\Application\Ports\PosicionesAsignablesPort;
use App\Modules\GestionPersonal\Application\Services\PersonalService;
use App\Modules\GestionPersonal\Domain\Repositories\ColaboradorRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Repositories\DesarrolloRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Repositories\VinculoLaboralRepositoryInterface;
use App\Modules\GestionPersonal\Infrastructure\Adapters\EstructuraFacadeLector;
use App\Modules\GestionPersonal\Infrastructure\Adapters\LectorDeCapacidadesDesarrollo;
use App\Modules\GestionPersonal\Infrastructure\Adapters\LectorDeDesarrolloEloquent;
use App\Modules\GestionPersonal\Infrastructure\Adapters\PosicionesAsignablesEstructura;
use App\Modules\GestionPersonal\Infrastructure\Adapters\SharedContextoDeTenant;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Repositories\EloquentAccesosDeCuenta;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Repositories\EloquentColaboradorRepository;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Repositories\EloquentDesarrolloRepository;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Repositories\EloquentLectorLaboral;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Repositories\EloquentVinculoLaboralRepository;
use Illuminate\Support\ServiceProvider;

class GestionPersonalServiceProvider extends ServiceProvider
{
    /**
     * Puertos (Domain\Repositories, Application\Ports) -> Adaptadores (Infrastructure\*).
     * Facade (Presentation\Facades) -> Servicio inter-módulos (Application\Services).
     */
    public function register(): void
    {
        $this->app->bind(ColaboradorRepositoryInterface::class, EloquentColaboradorRepository::class);
        $this->app->bind(DesarrolloRepositoryInterface::class, EloquentDesarrolloRepository::class);
        $this->app->bind(LectorDeDesarrolloPort::class, LectorDeDesarrolloEloquent::class);
        $this->app->bind(AccesosDeCuentaPort::class, EloquentAccesosDeCuenta::class);
        $this->app->bind(ContextoDeTenantPort::class, SharedContextoDeTenant::class);

        // GP-02 y GP-03: ingresos, lectores laborales y Desarrollo instalados.
        $this->app->bind(VinculoLaboralRepositoryInterface::class, EloquentVinculoLaboralRepository::class);
        $this->app->bind(LectorLaboralPort::class, EloquentLectorLaboral::class);
        $this->app->bind(LectorEstructuraPort::class, EstructuraFacadeLector::class);
        $this->app->bind(LectorDeCapacidadesPort::class, LectorDeCapacidadesDesarrollo::class);
        $this->app->bind(PosicionesAsignablesPort::class, PosicionesAsignablesEstructura::class);

        $this->app->singleton('personal-facade', fn ($app) => $app->make(PersonalService::class));
    }

    public function boot(): void
    {
        $routes = dirname(__DIR__, 2).'/Routes/api.php';
        if (file_exists($routes)) {
            $this->loadRoutesFrom($routes);
        }
    }
}
