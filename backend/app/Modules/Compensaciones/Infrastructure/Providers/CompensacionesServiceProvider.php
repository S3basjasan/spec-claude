<?php

namespace App\Modules\Compensaciones\Infrastructure\Providers;

use App\Modules\Compensaciones\Application\Ports\ColaboradoresPort;
use App\Modules\Compensaciones\Application\Ports\CompetenciasPort;
use App\Modules\Compensaciones\Application\Ports\EmpresaActualPort;
use App\Modules\Compensaciones\Application\Ports\EquipoPort;
use App\Modules\Compensaciones\Application\Ports\NombresDeUsuarioPort;
use App\Modules\Compensaciones\Application\Ports\UnidadesPort;
use App\Modules\Compensaciones\Application\Services\CategoriasService;
use App\Modules\Compensaciones\Application\Services\CompensacionesService;
use App\Modules\Compensaciones\Application\Services\ParametrosDelModulo;
use App\Modules\Compensaciones\Application\Services\PoliticaDeAcceso;
use App\Modules\Compensaciones\Application\Services\VisibilidadDeCategorias;
use App\Modules\Compensaciones\Domain\Repositories\AuditoriaCategoriaRepository;
use App\Modules\Compensaciones\Domain\Repositories\CategoriaRepository;
use App\Modules\Compensaciones\Domain\Repositories\ParametroRepository;
use App\Modules\Compensaciones\Infrastructure\Adapters\ColaboradoresDesdePersonal;
use App\Modules\Compensaciones\Infrastructure\Adapters\CompetenciasDesdeEstructura;
use App\Modules\Compensaciones\Infrastructure\Adapters\EmpresaActualPorSesionOConfiguracion;
use App\Modules\Compensaciones\Infrastructure\Adapters\EquipoDesdeConfiguracion;
use App\Modules\Compensaciones\Infrastructure\Adapters\NombresDeUsuarioDesdeShared;
use App\Modules\Compensaciones\Infrastructure\Adapters\UnidadesDesdeEstructura;
use App\Modules\Compensaciones\Infrastructure\Console\AsignarUnidadesIniciales;
use App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Repositories\EloquentAuditoriaCategoriaRepository;
use App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Repositories\EloquentCategoriaRepository;
use App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Repositories\EloquentParametroRepository;
use Illuminate\Support\ServiceProvider;

class CompensacionesServiceProvider extends ServiceProvider
{
    /**
     * Registra los bindings del módulo.
     *
     * Puertos (Domain\Repositories, Application\Ports) -> Adaptadores (Infrastructure\*).
     * Facades (Presentation\Facades) -> Servicios inter-módulos (Application\Services).
     */
    public function register(): void
    {
        $this->app->bind(CategoriaRepository::class, EloquentCategoriaRepository::class);
        $this->app->bind(AuditoriaCategoriaRepository::class, EloquentAuditoriaCategoriaRepository::class);
        $this->app->bind(CompetenciasPort::class, CompetenciasDesdeEstructura::class);
        $this->app->bind(UnidadesPort::class, UnidadesDesdeEstructura::class);
        $this->app->bind(EmpresaActualPort::class, EmpresaActualPorSesionOConfiguracion::class);
        $this->app->bind(ColaboradoresPort::class, ColaboradoresDesdePersonal::class);
        // PROVISIONAL: al publicarse líder y equipo en Gestión de Personal, cambiar solo este enlace por EquipoDesdePersonal.
        $this->app->bind(EquipoPort::class, EquipoDesdeConfiguracion::class);
        $this->app->bind(VisibilidadDeCategorias::class, fn ($app) => new VisibilidadDeCategorias(
            $app->make(PoliticaDeAcceso::class),
            $app->make(ColaboradoresPort::class),
            $app->make(EquipoPort::class),
            $app->make(UnidadesPort::class),
            (string) config('compensaciones.zona_horaria'),
        ));
        $this->app->bind(ParametroRepository::class, EloquentParametroRepository::class);
        $this->app->bind(NombresDeUsuarioPort::class, NombresDeUsuarioDesdeShared::class);
        $this->app->bind(ParametrosDelModulo::class, fn ($app) => new ParametrosDelModulo(
            $app->make(ParametroRepository::class),
            $app->make(CategoriaRepository::class),
            (string) config('compensaciones.zona_horaria'),
        ));

        // Binding de las Facades del módulo (Presentation\Facades).
        $this->app->singleton('compensaciones-facade', fn () => new CompensacionesService);
        $this->app->bind('compensaciones-categorias-facade', fn ($app) => $app->make(CategoriasService::class));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([AsignarUnidadesIniciales::class]);
        }

        // Carga las rutas del módulo si existen.
        $routes = dirname(__DIR__, 2).'/Routes/api.php';
        if (file_exists($routes)) {
            $this->loadRoutesFrom($routes);
        }
    }
}
