<?php

namespace App\Modules\Estructura\Infrastructure\Providers;

use App\Modules\Estructura\Application\Services\EstructuraService;
use App\Modules\Estructura\Domain\Repositories\CompetenciaRepositoryInterface;
use App\Modules\Estructura\Domain\Repositories\ComportamientoRepositoryInterface;
use App\Modules\Estructura\Domain\Repositories\PreguntaRepositoryInterface;
use App\Modules\Estructura\Domain\Repositories\PuestoRepositoryInterface;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Repositories\EloquentCompetenciaRepository;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Repositories\EloquentComportamientoRepository;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Repositories\EloquentPreguntaRepository;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Repositories\EloquentPuestoRepository;
use Illuminate\Support\ServiceProvider;

class EstructuraServiceProvider extends ServiceProvider
{
    /**
     * Registra los bindings del módulo.
     *
     * Puertos (Domain\Repositories) -> Adaptadores (Infrastructure\*).
     * Facade (Presentation\Facades) -> Servicio inter-módulos (Application\Services).
     */
    public function register(): void
    {
        $this->app->bind(CompetenciaRepositoryInterface::class, EloquentCompetenciaRepository::class);
        $this->app->bind(ComportamientoRepositoryInterface::class, EloquentComportamientoRepository::class);
        $this->app->bind(PreguntaRepositoryInterface::class, EloquentPreguntaRepository::class);
        $this->app->bind(PuestoRepositoryInterface::class, EloquentPuestoRepository::class);

        // Binding de la Facade del módulo (Presentation\Facades).
        $this->app->singleton('estructura-facade', fn ($app) => $app->make(EstructuraService::class));
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
