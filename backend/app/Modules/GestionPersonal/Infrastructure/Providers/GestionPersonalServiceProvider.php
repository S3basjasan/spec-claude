<?php

namespace App\Modules\GestionPersonal\Infrastructure\Providers;

use App\Modules\GestionPersonal\Application\Ports\AccesosDeCuentaPort;
use App\Modules\GestionPersonal\Application\Ports\ContextoDeTenantPort;
use App\Modules\GestionPersonal\Application\Ports\LectorDeCapacidadesPort;
use App\Modules\GestionPersonal\Application\Ports\LectorEstructuraPort;
use App\Modules\GestionPersonal\Application\Ports\LectorLaboralPort;
use App\Modules\GestionPersonal\Application\Ports\PosicionesAsignablesPort;
use App\Modules\GestionPersonal\Application\Services\PersonalService;
use App\Modules\GestionPersonal\Domain\Repositories\ColaboradorRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Repositories\VinculoLaboralRepositoryInterface;
use App\Modules\GestionPersonal\Infrastructure\Adapters\EstructuraFacadeLector;
use App\Modules\GestionPersonal\Infrastructure\Adapters\LectorDeCapacidadesGp02;
use App\Modules\GestionPersonal\Infrastructure\Adapters\PosicionesAsignablesEstructura;
use App\Modules\GestionPersonal\Infrastructure\Adapters\SharedContextoDeTenant;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Repositories\EloquentAccesosDeCuenta;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Repositories\EloquentColaboradorRepository;
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
        $this->app->bind(AccesosDeCuentaPort::class, EloquentAccesosDeCuenta::class);
        $this->app->bind(ContextoDeTenantPort::class, SharedContextoDeTenant::class);

        // GP-02: lectores reales de vínculos, de Estructura y de capacidades (Desarrollo, Documentos y Exportación
        // siguen sin instalar).
        $this->app->bind(VinculoLaboralRepositoryInterface::class, EloquentVinculoLaboralRepository::class);
        $this->app->bind(LectorLaboralPort::class, EloquentLectorLaboral::class);
        $this->app->bind(LectorEstructuraPort::class, EstructuraFacadeLector::class);
        $this->app->bind(LectorDeCapacidadesPort::class, LectorDeCapacidadesGp02::class);
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
