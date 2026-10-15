<?php

namespace App\Modules\Desarrollo\Infrastructure\Providers;

use App\Modules\Desarrollo\Application\Ports\BrechasActivasInterface;
use App\Modules\Desarrollo\Application\Ports\CompetenciasInterface;
use App\Modules\Desarrollo\Application\Ports\NivelesDeColaboradorInterface;
use App\Modules\Desarrollo\Application\Ports\NombresDeUsuarioInterface;
use App\Modules\Desarrollo\Application\Services\DesarrolloService;
use App\Modules\Desarrollo\Domain\Repositories\CatalogoFormativoRepositoryInterface;
use App\Modules\Desarrollo\Infrastructure\Adapters\BrechasActivasSinDatos;
use App\Modules\Desarrollo\Infrastructure\Adapters\CompetenciasDesdeEstructura;
use App\Modules\Desarrollo\Infrastructure\Adapters\NivelesDeColaboradorSinDatos;
use App\Modules\Desarrollo\Infrastructure\Adapters\NombresDeUsuarioDesdeShared;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Repositories\EloquentCatalogoFormativoRepository;
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

        $this->app->bind(CompetenciasInterface::class, CompetenciasDesdeEstructura::class);

        // Adaptadores provisionales hasta que existan Gestion de Personal y la DNF.
        $this->app->bind(NivelesDeColaboradorInterface::class, NivelesDeColaboradorSinDatos::class);
        $this->app->bind(BrechasActivasInterface::class, BrechasActivasSinDatos::class);
        $this->app->bind(NombresDeUsuarioInterface::class, NombresDeUsuarioDesdeShared::class);

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
