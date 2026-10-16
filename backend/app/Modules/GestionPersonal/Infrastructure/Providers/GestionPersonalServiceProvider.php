<?php

namespace App\Modules\GestionPersonal\Infrastructure\Providers;

use App\Modules\GestionPersonal\Application\Ports\AccesosDeCuentaPort;
use App\Modules\GestionPersonal\Application\Ports\ContextoDeTenantPort;
use App\Modules\GestionPersonal\Application\Ports\LectorDeCapacidadesPort;
use App\Modules\GestionPersonal\Application\Ports\LectorEstructuraPort;
use App\Modules\GestionPersonal\Application\Ports\LectorLaboralPort;
use App\Modules\GestionPersonal\Application\Services\PersonalService;
use App\Modules\GestionPersonal\Domain\Repositories\ColaboradorRepositoryInterface;
use App\Modules\GestionPersonal\Infrastructure\Adapters\LectorDeCapacidadesNoInstalado;
use App\Modules\GestionPersonal\Infrastructure\Adapters\LectorEstructuraNoInstalado;
use App\Modules\GestionPersonal\Infrastructure\Adapters\LectorLaboralNoInstalado;
use App\Modules\GestionPersonal\Infrastructure\Adapters\SharedContextoDeTenant;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Repositories\EloquentAccesosDeCuenta;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Repositories\EloquentColaboradorRepository;
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

        // GP-02, Estructura (unidades) y GP-03/05/06 aún no existen: se sustituyen estos adaptadores al instalarse.
        $this->app->bind(LectorLaboralPort::class, LectorLaboralNoInstalado::class);
        $this->app->bind(LectorEstructuraPort::class, LectorEstructuraNoInstalado::class);
        $this->app->bind(LectorDeCapacidadesPort::class, LectorDeCapacidadesNoInstalado::class);

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
