<?php

namespace App\Modules\Compensaciones\Infrastructure\Providers;

use App\Modules\Compensaciones\Application\Ports\CompetenciasPort;
use App\Modules\Compensaciones\Application\Ports\NombresDeUsuarioPort;
use App\Modules\Compensaciones\Application\Services\CategoriasService;
use App\Modules\Compensaciones\Application\Services\CompensacionesService;
use App\Modules\Compensaciones\Application\Services\ParametrosDelModulo;
use App\Modules\Compensaciones\Domain\Repositories\AuditoriaCategoriaRepository;
use App\Modules\Compensaciones\Domain\Repositories\CategoriaRepository;
use App\Modules\Compensaciones\Domain\Repositories\ParametroRepository;
use App\Modules\Compensaciones\Infrastructure\Adapters\CompetenciasDesdeEstructura;
use App\Modules\Compensaciones\Infrastructure\Adapters\NombresDeUsuarioDesdeShared;
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
        // Carga las rutas del módulo si existen.
        $routes = dirname(__DIR__, 2).'/Routes/api.php';
        if (file_exists($routes)) {
            $this->loadRoutesFrom($routes);
        }
    }
}
