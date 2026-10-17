<?php

namespace App\Modules\Desarrollo\Infrastructure\Providers;

use App\Modules\Desarrollo\Application\Ports\AccionesDePlanInterface;
use App\Modules\Desarrollo\Application\Ports\AprobadoresInterface;
use App\Modules\Desarrollo\Application\Ports\AsistenciaInterface;
use App\Modules\Desarrollo\Application\Ports\AsistenciaRegistradaInterface;
use App\Modules\Desarrollo\Application\Ports\BrechasActivasInterface;
use App\Modules\Desarrollo\Application\Ports\CertificadoPdfInterface;
use App\Modules\Desarrollo\Application\Ports\ColaboradoresEvaluablesInterface;
use App\Modules\Desarrollo\Application\Ports\ColaboradoresVigentesInterface;
use App\Modules\Desarrollo\Application\Ports\CompetenciasInterface;
use App\Modules\Desarrollo\Application\Ports\CuentaColaboradorInterface;
use App\Modules\Desarrollo\Application\Ports\DepartamentosInterface;
use App\Modules\Desarrollo\Application\Ports\NivelesActualesInterface;
use App\Modules\Desarrollo\Application\Ports\NivelesDeColaboradorInterface;
use App\Modules\Desarrollo\Application\Ports\NombreDeColaboradorInterface;
use App\Modules\Desarrollo\Application\Ports\NombresDeReferenciaInterface;
use App\Modules\Desarrollo\Application\Ports\NombresDeUsuarioInterface;
use App\Modules\Desarrollo\Application\Ports\PerfilesDePuestoInterface;
use App\Modules\Desarrollo\Application\Ports\PlanDeFormacionInterface;
use App\Modules\Desarrollo\Application\Ports\RelojInterface;
use App\Modules\Desarrollo\Application\Services\DesarrolloService;
use App\Modules\Desarrollo\Domain\Repositories\ActividadRepositoryInterface;
use App\Modules\Desarrollo\Domain\Repositories\CatalogoFormativoRepositoryInterface;
use App\Modules\Desarrollo\Domain\Repositories\DnfRepositoryInterface;
use App\Modules\Desarrollo\Domain\Repositories\MicrocontenidoRepositoryInterface;
use App\Modules\Desarrollo\Domain\Repositories\PlanRepositoryInterface;
use App\Modules\Desarrollo\Domain\Repositories\ProgresoMicroRepositoryInterface;
use App\Modules\Desarrollo\Infrastructure\Adapters\AccionesDesdePlan;
use App\Modules\Desarrollo\Infrastructure\Adapters\AprobadoresDesdeShared;
use App\Modules\Desarrollo\Infrastructure\Adapters\AsistenciaDesdeActividades;
use App\Modules\Desarrollo\Infrastructure\Adapters\AsistenciaRegistradaDesdeActividades;
use App\Modules\Desarrollo\Infrastructure\Adapters\BrechasActivasDesdeDnf;
use App\Modules\Desarrollo\Infrastructure\Adapters\CertificadoPdfDompdf;
use App\Modules\Desarrollo\Infrastructure\Adapters\ColaboradoresEvaluablesSinDatos;
use App\Modules\Desarrollo\Infrastructure\Adapters\ColaboradoresVigentesDesdePersonal;
use App\Modules\Desarrollo\Infrastructure\Adapters\CompetenciasDesdeEstructura;
use App\Modules\Desarrollo\Infrastructure\Adapters\CuentaColaboradorDesdePersonal;
use App\Modules\Desarrollo\Infrastructure\Adapters\DepartamentosDesdeEstructura;
use App\Modules\Desarrollo\Infrastructure\Adapters\NivelesActualesSinDatos;
use App\Modules\Desarrollo\Infrastructure\Adapters\NivelesDeColaboradorSinDatos;
use App\Modules\Desarrollo\Infrastructure\Adapters\NombreDeColaboradorDesdePersonal;
use App\Modules\Desarrollo\Infrastructure\Adapters\NombresDeReferenciaDesdeFacades;
use App\Modules\Desarrollo\Infrastructure\Adapters\NombresDeUsuarioDesdeShared;
use App\Modules\Desarrollo\Infrastructure\Adapters\PerfilesDePuestoDesdeEstructura;
use App\Modules\Desarrollo\Infrastructure\Adapters\PlanDeFormacionDesdePlanes;
use App\Modules\Desarrollo\Infrastructure\Adapters\Pruebas\ColaboradoresEvaluablesDePrueba;
use App\Modules\Desarrollo\Infrastructure\Adapters\Pruebas\ColaboradoresVigentesDePrueba;
use App\Modules\Desarrollo\Infrastructure\Adapters\Pruebas\NivelesActualesDePrueba;
use App\Modules\Desarrollo\Infrastructure\Adapters\RelojDelSistema;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Repositories\EloquentActividadRepository;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Repositories\EloquentCatalogoFormativoRepository;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Repositories\EloquentDnfRepository;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Repositories\EloquentMicrocontenidoRepository;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Repositories\EloquentPlanRepository;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Repositories\EloquentProgresoMicroRepository;
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
        $this->app->bind(PlanRepositoryInterface::class, EloquentPlanRepository::class);

        // Micro-learning (016): contenidos y aprendizaje del colaborador; el nombre del certificado viene de Personal.
        $this->app->bind(MicrocontenidoRepositoryInterface::class, EloquentMicrocontenidoRepository::class);
        $this->app->bind(ProgresoMicroRepositoryInterface::class, EloquentProgresoMicroRepository::class);
        $this->app->bind(NombreDeColaboradorInterface::class, NombreDeColaboradorDesdePersonal::class);
        $this->app->bind(CertificadoPdfInterface::class, CertificadoPdfDompdf::class);

        // Plan de formación anual (013): datos de otros módulos por sus Facades, tras puertos propios.
        $this->app->bind(ColaboradoresVigentesInterface::class, ColaboradoresVigentesDesdePersonal::class);
        $this->app->bind(AprobadoresInterface::class, AprobadoresDesdeShared::class);
        $this->app->bind(RelojInterface::class, RelojDelSistema::class);
        // Actividades formativas (017): la asistencia real alimenta el progreso del plan y bloquea quitar participantes con registro.
        $this->app->bind(ActividadRepositoryInterface::class, EloquentActividadRepository::class);
        $this->app->bind(AccionesDePlanInterface::class, AccionesDesdePlan::class);
        $this->app->bind(DepartamentosInterface::class, DepartamentosDesdeEstructura::class);
        $this->app->bind(AsistenciaInterface::class, AsistenciaDesdeActividades::class);
        $this->app->bind(AsistenciaRegistradaInterface::class, AsistenciaRegistradaDesdeActividades::class);

        $this->app->bind(CompetenciasInterface::class, CompetenciasDesdeEstructura::class);
        $this->app->bind(PerfilesDePuestoInterface::class, PerfilesDePuestoDesdeEstructura::class);
        $this->app->bind(CuentaColaboradorInterface::class, CuentaColaboradorDesdePersonal::class);
        $this->app->bind(NombresDeReferenciaInterface::class, NombresDeReferenciaDesdeFacades::class);
        $this->app->bind(BrechasActivasInterface::class, BrechasActivasDesdeDnf::class);

        // PROVISIONAL: Gestion de Personal (GP-02) aun no publica puesto ni nivel actual. El Plan de Formacion (013) ya existe.
        $this->app->bind(NivelesDeColaboradorInterface::class, NivelesDeColaboradorSinDatos::class);
        $this->app->bind(NombresDeUsuarioInterface::class, NombresDeUsuarioDesdeShared::class);
        $this->app->bind(ColaboradoresEvaluablesInterface::class, ColaboradoresEvaluablesSinDatos::class);
        $this->app->bind(NivelesActualesInterface::class, NivelesActualesSinDatos::class);
        $this->app->bind(PlanDeFormacionInterface::class, PlanDeFormacionDesdePlanes::class);

        // Solo en el entorno e2e: adaptadores de prueba para que los E2E generen una DNF real sin GP-02.
        if ($this->app->environment('e2e')) {
            $this->app->bind(ColaboradoresEvaluablesInterface::class, ColaboradoresEvaluablesDePrueba::class);
            $this->app->bind(NivelesActualesInterface::class, NivelesActualesDePrueba::class);
            $this->app->bind(ColaboradoresVigentesInterface::class, ColaboradoresVigentesDePrueba::class);
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
