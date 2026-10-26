<?php

namespace App\Modules\GestionPersonal\Infrastructure\Providers;

use App\Modules\GestionPersonal\Application\Ports\AccesosDeCuentaPort;
use App\Modules\GestionPersonal\Application\Ports\AlmacenDeDocumentosPort;
use App\Modules\GestionPersonal\Application\Ports\CatalogoDeCategoriasPort;
use App\Modules\GestionPersonal\Application\Ports\ContextoDeLecturaPort;
use App\Modules\GestionPersonal\Application\Ports\ContextoDeTenantPort;
use App\Modules\GestionPersonal\Application\Ports\EvaluacionDelDestinoPort;
use App\Modules\GestionPersonal\Application\Ports\LectorDeCapacidadesPort;
use App\Modules\GestionPersonal\Application\Ports\LectorDeDesarrolloPort;
use App\Modules\GestionPersonal\Application\Ports\LectorEstructuraPort;
use App\Modules\GestionPersonal\Application\Ports\LectorLaboralPort;
use App\Modules\GestionPersonal\Application\Ports\PosicionesAsignablesPort;
use App\Modules\GestionPersonal\Application\Ports\RelojPort;
use App\Modules\GestionPersonal\Application\Ports\RenderizadorDePdfPort;
use App\Modules\GestionPersonal\Application\Ports\TransaccionPort;
use App\Modules\GestionPersonal\Application\Ports\ValidadorDePdfPort;
use App\Modules\GestionPersonal\Application\Services\PersonalService;
use App\Modules\GestionPersonal\Application\Services\PoliticasService;
use App\Modules\GestionPersonal\Domain\Repositories\ActuacionRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Repositories\CategoriaRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Repositories\ColaboradorRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Repositories\DesarrolloRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Repositories\DocumentoRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Repositories\PoliticaRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Repositories\VinculoLaboralRepositoryInterface;
use App\Modules\GestionPersonal\Infrastructure\Adapters\AlmacenDeDocumentosDisco;
use App\Modules\GestionPersonal\Infrastructure\Adapters\CatalogoDeCategoriasEloquent;
use App\Modules\GestionPersonal\Infrastructure\Adapters\ContextoDeLecturaSnapshot;
use App\Modules\GestionPersonal\Infrastructure\Adapters\EstructuraFacadeLector;
use App\Modules\GestionPersonal\Infrastructure\Adapters\EvaluacionDelDestinoNoInstalada;
use App\Modules\GestionPersonal\Infrastructure\Adapters\LectorDeCapacidadesDesarrollo;
use App\Modules\GestionPersonal\Infrastructure\Adapters\LectorDeDesarrolloEloquent;
use App\Modules\GestionPersonal\Infrastructure\Adapters\PosicionesAsignablesEstructura;
use App\Modules\GestionPersonal\Infrastructure\Adapters\RelojDelSistema;
use App\Modules\GestionPersonal\Infrastructure\Adapters\RenderizadorDePdfDompdf;
use App\Modules\GestionPersonal\Infrastructure\Adapters\SharedContextoDeTenant;
use App\Modules\GestionPersonal\Infrastructure\Adapters\TransaccionLaravel;
use App\Modules\GestionPersonal\Infrastructure\Adapters\ValidadorDePdfEstructural;
use App\Modules\GestionPersonal\Infrastructure\Console\AplicarMovimientosProgramadosCommand;
use App\Modules\GestionPersonal\Infrastructure\Console\LimpiarObjetosHuerfanosCommand;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Repositories\EloquentAccesosDeCuenta;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Repositories\EloquentActuacionRepository;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Repositories\EloquentCategoriaRepository;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Repositories\EloquentColaboradorRepository;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Repositories\EloquentDesarrolloRepository;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Repositories\EloquentDocumentoRepository;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Repositories\EloquentLectorLaboral;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Repositories\EloquentPoliticaRepository;
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
        $this->app->bind(CategoriaRepositoryInterface::class, EloquentCategoriaRepository::class);
        $this->app->bind(CatalogoDeCategoriasPort::class, CatalogoDeCategoriasEloquent::class);
        $this->app->bind(DocumentoRepositoryInterface::class, EloquentDocumentoRepository::class);
        $this->app->bind(AlmacenDeDocumentosPort::class, AlmacenDeDocumentosDisco::class);
        $this->app->bind(ValidadorDePdfPort::class, ValidadorDePdfEstructural::class);
        $this->app->bind(TransaccionPort::class, TransaccionLaravel::class);
        $this->app->bind(ContextoDeLecturaPort::class, ContextoDeLecturaSnapshot::class);
        $this->app->bind(RenderizadorDePdfPort::class, RenderizadorDePdfDompdf::class);
        $this->app->bind(PoliticaRepositoryInterface::class, EloquentPoliticaRepository::class);
        $this->app->bind(RelojPort::class, RelojDelSistema::class);
        $this->app->bind(AccesosDeCuentaPort::class, EloquentAccesosDeCuenta::class);
        $this->app->bind(ContextoDeTenantPort::class, SharedContextoDeTenant::class);

        // GP-02 y GP-03: ingresos, lectores laborales y Desarrollo instalados.
        $this->app->bind(VinculoLaboralRepositoryInterface::class, EloquentVinculoLaboralRepository::class);
        $this->app->bind(LectorLaboralPort::class, EloquentLectorLaboral::class);
        $this->app->bind(LectorEstructuraPort::class, EstructuraFacadeLector::class);
        $this->app->bind(LectorDeCapacidadesPort::class, LectorDeCapacidadesDesarrollo::class);
        $this->app->bind(PosicionesAsignablesPort::class, PosicionesAsignablesEstructura::class);

        // Movimientos y novedades (feature 020). Sin contrato de evaluación instalado, la aprobación de movimientos queda impedida.
        $this->app->bind(ActuacionRepositoryInterface::class, EloquentActuacionRepository::class);
        $this->app->bind(EvaluacionDelDestinoPort::class, EvaluacionDelDestinoNoInstalada::class);

        $this->app->singleton('personal-facade', fn ($app) => $app->make(PersonalService::class));
        $this->app->singleton('politicas-facade', fn ($app) => $app->make(PoliticasService::class));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([LimpiarObjetosHuerfanosCommand::class, AplicarMovimientosProgramadosCommand::class]);
        }

        $this->loadViewsFrom(dirname(__DIR__).'/Pdf/plantillas', 'gp-exportacion');

        $routes = dirname(__DIR__, 2).'/Routes/api.php';
        if (file_exists($routes)) {
            $this->loadRoutesFrom($routes);
        }
    }
}
