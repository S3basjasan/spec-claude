<?php

namespace App\Modules\Shared\Infrastructure\Providers;

use App\Modules\Shared\Application\Ports\EmisorTokenInterface;
use App\Modules\Shared\Application\Ports\HasherInterface;
use App\Modules\Shared\Application\Services\SharedService;
use App\Modules\Shared\Domain\Repositories\UsuarioRepositoryInterface;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Repositories\EloquentUsuarioRepository;
use App\Modules\Shared\Infrastructure\Security\LaravelHasher;
use App\Modules\Shared\Infrastructure\Security\SanctumEmisorToken;
use Illuminate\Support\ServiceProvider;

class SharedServiceProvider extends ServiceProvider
{
    /**
     * Registra los bindings del módulo.
     *
     * Puertos (Domain\Repositories, Application\Ports) -> Adaptadores (Infrastructure\*).
     * Facade (Presentation\Facades) -> Servicio inter-módulos (Application\Services).
     */
    public function register(): void
    {
        $this->app->bind(UsuarioRepositoryInterface::class, EloquentUsuarioRepository::class);
        $this->app->bind(HasherInterface::class, LaravelHasher::class);
        $this->app->bind(EmisorTokenInterface::class, SanctumEmisorToken::class);

        // Binding de la Facade del módulo (Presentation\Facades).
        $this->app->singleton('shared-facade', fn ($app) => $app->make(SharedService::class));
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
