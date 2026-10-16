<?php

namespace App\Modules\Desarrollo\Infrastructure\Providers;

use App\Modules\Desarrollo\Application\Ports\BrechasActivasInterface;
use App\Modules\Desarrollo\Application\Ports\ColaboradoresEvaluablesInterface;
use App\Modules\Desarrollo\Application\Ports\CompetenciasInterface;
use App\Modules\Desarrollo\Application\Ports\CuentaColaboradorInterface;
use App\Modules\Desarrollo\Application\Ports\NivelesActualesInterface;
use App\Modules\Desarrollo\Application\Ports\NivelesDeColaboradorInterface;
use App\Modules\Desarrollo\Application\Ports\NombresDeReferenciaInterface;
use App\Modules\Desarrollo\Application\Ports\NombresDeUsuarioInterface;
use App\Modules\Desarrollo\Application\Ports\PerfilesDePuestoInterface;
use App\Modules\Desarrollo\Application\Ports\PlanDeFormacionInterface;
use App\Modules\Desarrollo\Application\Services\DesarrolloService;
use App\Modules\Desarrollo\Domain\Repositories\CatalogoFormativoRepositoryInterface;
use App\Modules\Desarrollo\Domain\Repositories\DnfRepositoryInterface;
use App\Modules\Desarrollo\Infrastructure\Adapters\BrechasActivasDesdeDnf;
use App\Modules\Desarrollo\Infrastructure\Adapters\ColaboradoresEvaluablesSinDatos;
use App\Modules\Desarrollo\Infrastructure\Adapters\CompetenciasDesdeEstructura;
use App\Modules\Desarrollo\Infrastructure\Adapters\CuentaColaboradorDesdePersonal;
use App\Modules\Desarrollo\Infrastructure\Adapters\NivelesActualesSinDatos;
use App\Modules\Desarrollo\Infrastructure\Adapters\NivelesDeColaboradorSinDatos;
use App\Modules\Desarrollo\Infrastructure\Adapters\NombresDeReferenciaDesdeFacades;
use App\Modules\Desarrollo\Infrastructure\Adapters\NombresDeUsuarioDesdeShared;
use App\Modules\Desarrollo\Infrastructure\Adapters\PerfilesDePuestoDesdeEstructura;
use App\Modules\Desarrollo\Infrastructure\Adapters\PlanDeFormacionNoDisponible;
use App\Modules\Desarrollo\Infrastructure\Adapters\Pruebas\ColaboradoresEvaluablesDePrueba;
use App\Modules\Desarrollo\Infrastructure\Adapters\Pruebas\NivelesActualesDePrueba;
use App\Modules\Desarrollo\Infrastructure\Adapters\Pruebas\PlanDeFormacionDePrueba;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Repositories\EloquentCatalogoFormativoRepository;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Repositories\EloquentDnfRepository;
use Illuminate\Support\ServiceProvider;

class DesarrolloServiceProvider extends ServiceProvider
{
    /**
     * Puertos (Domain\Repositories, Application\Ports) -> Adaptadores (Infrastructure\*).
     * Facade (Presentation\Facades) -> Servicio inter-modulos (Application\Services).
     */
    public function register(): void
    {
        $this->app->bind(CatalogoFormativoRepositoryInterface::class, EloquentCatalogoFormativoRepository::class);
        $this->app->bind(DnfRepositoryInterface::class, EloquentDnfRepository::class);

        $this->app->bind(CompetenciasInterface::class, CompetenciasDesdeEstructura::class);
        $this->app->bind(PerfilesDePuestoInterface::class, PerfilesDePuestoDesdeEstructura::class);
        $this->app->bind(CuentaColaboradorInterface::class, CuentaColaboradorDesdePersonal::class);
        $this->app->bind(NombresDeReferenciaInterface::class, NombresDeReferenciaDesdeFacades::class);
        $this->app->bind(BrechasActivasInterface::class, BrechasActivasDesdeDnf::class);

        // PROVISIONAL: Gestion de Personal (GP-02) y Plan de Formacion aun no existen.
        $this->app->bind(NivelesDeColaboradorInterface::class, NivelesDeColaboradorSinDatos::class);
        $this->app->bind(NombresDeUsuarioInterface::class, NombresDeUsuarioDesdeShared::class);
        $this->app->bind(ColaboradoresEvaluablesInterface::class, ColaboradoresEvaluablesSinDatos::class);
        $this->app->bind(NivelesActualesInterface::class, NivelesActualesSinDatos::class);
        $this->app->bind(PlanDeFormacionInterface::class, PlanDeFormacionNoDisponible::class);

        // Solo en el entorno e2e: adaptadores de prueba para que los E2E generen una DNF real sin GP-02 ni Plan de Formacion.
        if ($this->app->environment('e2e')) {
            $this->app->bind(ColaboradoresEvaluablesInterface::class, ColaboradoresEvaluablesDePrueba::class);
            $this->app->bind(NivelesActualesInterface::class, NivelesActualesDePrueba::class);
            $this->app->bind(PlanDeFormacionInterface::class, PlanDeFormacionDePrueba::class);
        }

        $this->app->singleton('desarrollo-facade', fn ($app) => $app->make(DesarrolloService::class));
    }

    public function boot(): void
    {
        $routes = dirname(__DIR__, 2).'/Routes/api.php';
        if (file_exists($routes)) {
            $this->loadRoutesFrom($routes);
        }
    }
}
