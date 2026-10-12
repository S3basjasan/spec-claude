<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeModuleCommand extends Command
{
    /**
     * El nombre y firma del comando en la terminal.
     */
    protected $signature = 'make:module {name}';

    /**
     * La descripción del comando.
     */
    protected $description = 'Crea la estructura de Clean Architecture para un nuevo módulo (C4 Nivel 3)';

    /**
     * Ejecuta el comando.
     */
    public function handle()
    {
        $rawName = $this->argument('name');
        $moduleName = Str::studly($rawName);
        $basePath = app_path("Modules/{$moduleName}");

        if (! preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $moduleName)) {
            $this->error("Nombre de módulo inválido '{$rawName}'. Usa solo letras/números sin tildes ni ñ (ej: Estructura, Seleccion, Compensaciones).");

            return Command::FAILURE;
        }

        if (File::exists($basePath)) {
            $this->error("El módulo {$moduleName} ya existe.");

            return Command::FAILURE;
        }

        // Estructura fiel al C4 Nivel 3 (Módulo Estructura Empresarial) + Clean Architecture.
        // Presentación = inbound adapters (Controladores REST + Facade inter-módulos).
        // Aplicación = Casos de Uso + DTOs.
        // Dominio = Modelo de Dominio (entidades + puertos/contratos).
        // Infraestructura = outbound adapters (Eloquent + IA Guzzle + Storage Flysystem + Audit Listener/Syslog).
        $directories = [
            // Presentación — [Component: Laravel Controller] + [Component: Laravel Facade]
            'Presentation/Controllers',
            'Presentation/Requests',
            'Presentation/Resources',
            'Presentation/Facades',
            // Aplicación — [Component: Plain Old PHP Object] Casos de Uso
            'Application/UseCases',
            'Application/DTOs',
            // Aplicación — Servicio inter-módulos detrás de la Facade [Inter-module Call]
            'Application/Services',
            // Dominio — [Component: Plain Old PHP Object] Modelo de Dominio
            'Domain/Entities',
            'Domain/ValueObjects',
            'Domain/Repositories',
            'Domain/Events',
            // Infraestructura — [Component: Laravel Eloquent] Repositorios
            'Infrastructure/Persistence/Eloquent/Models',
            'Infrastructure/Persistence/Eloquent/Repositories',
            // Infraestructura — [Component: Guzzle HTTP Client] Adaptador de IA
            'Infrastructure/AI',
            // Infraestructura — [Component: Laravel Storage (Flysystem)] Adaptador de Almacenamiento (MinIO docs, Sube y descarga [AWS SDK/HTTPS])
            'Infrastructure/Storage',
            // Infraestructura — [Component: Laravel Event Listener + Log Syslog] Adaptador de Auditoría (Sistema de Logs, Envía registros a [Syslog/TCP])
            'Infrastructure/Audit',
            // Infraestructura — cableado DI del módulo
            'Infrastructure/Providers',
            // Rutas del módulo
            'Routes',

        ];

        // Crear los directorios
        foreach ($directories as $dir) {
            File::makeDirectory("{$basePath}/{$dir}", 0755, true, true);
        }

        // Archivos base que materializan los componentes del diagrama
        $this->createService($basePath, $moduleName);
        $this->createFacade($basePath, $moduleName);
        $this->createServiceProvider($basePath, $moduleName);
        $this->createRoutes($basePath, $moduleName);

        $providerClass = "App\\Modules\\{$moduleName}\\Infrastructure\\Providers\\{$moduleName}ServiceProvider";
        $this->info("Módulo {$moduleName} creado con estructura C4 Nivel 3 + Clean Architecture.");
        $this->line("Siguiente paso: registra {$providerClass} en bootstrap/providers.php.");

        return Command::SUCCESS;
    }

    /**
     * Genera el servicio inter-módulos (Application).
     * C4: clase real detrás de [Component: Laravel Facade].
     */
    protected function createService(string $basePath, string $moduleName): void
    {
        $namespace = "App\\Modules\\{$moduleName}\\Application\\Services";
        $class = "{$moduleName}Service";

        $stub = <<<EOT
<?php

namespace {$namespace};

/**
 * Servicio inter-módulos del Módulo {$moduleName}.
 *
 * C4 Nivel 3 — clase real detrás de [Component: Laravel Facade].
 * Expande con métodos get* que delegan a Application\\UseCases.
 */
class {$class}
{
    /**
     * Resumen mínimo para verificar el cableado Facade -> Servicio.
     *
     * @return array<string, mixed>
     */
    public function getResumen(): array
    {
        return ['modulo' => '{$moduleName}'];
    }
}
EOT;

        File::put("{$basePath}/Application/Services/{$class}.php", $stub);
    }

    /**
     * Genera la Facade del módulo (Presentación).
     * C4: [Component: Laravel Facade] interfaz simplificada para otros módulos.
     */
    protected function createFacade(string $basePath, string $moduleName): void
    {
        $facadeName = "{$moduleName}Facade";
        $namespace = "App\\Modules\\{$moduleName}\\Presentation\\Facades";
        $serviceClass = "App\\Modules\\{$moduleName}\\Application\\Services\\{$moduleName}Service";

        // Binding en kebab-case (ej: estructura-facade)
        $accessor = Str::kebab($moduleName).'-facade';

        $stub = <<<EOT
<?php

namespace {$namespace};

use Illuminate\Support\Facades\Facade;

/**
 * @method static array getResumen() Resumen mínimo del módulo.
 *
 * @see \\{$serviceClass}
 */
class {$facadeName} extends Facade
{
    /**
     * Obtiene el nombre registrado del componente en el Service Container.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return '{$accessor}';
    }
}
EOT;

        File::put("{$basePath}/Presentation/Facades/{$facadeName}.php", $stub);
    }

    /**
     * Genera el ServiceProvider del módulo (Infraestructura).
     * Cablea puertos del Dominio con adaptadores de Infraestructura.
     */
    protected function createServiceProvider(string $basePath, string $moduleName): void
    {
        $namespace = "App\\Modules\\{$moduleName}\\Infrastructure\\Providers";
        $class = "{$moduleName}ServiceProvider";
        $serviceClass = "App\\Modules\\{$moduleName}\\Application\\Services\\{$moduleName}Service";
        $accessor = Str::kebab($moduleName).'-facade';

        $stub = <<<EOT
<?php

namespace {$namespace};

use {$serviceClass};
use Illuminate\Support\ServiceProvider;

class {$class} extends ServiceProvider
{
    /**
     * Registra los bindings del módulo.
     *
     * Puertos (Domain\\Repositories) -> Adaptadores (Infrastructure\\*).
     * Facade (Presentation\\Facades) -> Servicio inter-módulos (Application\\Services).
     * Ejemplo:
     * \$this->app->bind(
     *     \\App\\Modules\\{$moduleName}\\Domain\\Repositories\\ExampleRepositoryInterface::class,
     *     \\App\\Modules\\{$moduleName}\\Infrastructure\\Persistence\\Eloquent\\Repositories\\EloquentExampleRepository::class
     * );
     */
    public function register(): void
    {
        // Binding de la Facade del módulo (Presentation\\Facades).
        \$this->app->singleton('{$accessor}', fn () => new {$moduleName}Service);
    }

    public function boot(): void
    {
        // Carga las rutas del módulo si existen.
        \$routes = dirname(__DIR__, 2) . '/Routes/api.php';
        if (file_exists(\$routes)) {
            \$this->loadRoutesFrom(\$routes);
        }
    }
}
EOT;

        File::put("{$basePath}/Infrastructure/Providers/{$class}.php", $stub);
    }

    /**
     * Genera el archivo de rutas del módulo.
     */
    protected function createRoutes(string $basePath, string $moduleName): void
    {
        $prefix = Str::kebab($moduleName);

        $stub = <<<EOT
<?php

use Illuminate\Support\Facades\Route;

// Rutas del módulo {$moduleName}.
// Los controladores viven en Presentation\\Controllers y delegan a Application\\UseCases.
Route::prefix('api/{$prefix}')->middleware('api')->group(function () {
    // Route::get('/puestos', [\\App\\Modules\\{$moduleName}\\Presentation\\Controllers\\ExampleController::class, 'index']);
});
EOT;

        File::put("{$basePath}/Routes/api.php", $stub);
    }
}
