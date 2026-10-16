<?php

use App\Modules\GestionPersonal\Application\Ports\LectorEstructuraPort;
use App\Modules\GestionPersonal\Application\Ports\LectorLaboralPort;
use App\Modules\GestionPersonal\Domain\ValueObjects\Lectura;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\ColaboradorModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\TenantModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

/**
 * Helpers de la feature 005 (fichas de colaboradores). Datos sintéticos.
 */
function tenantDos(): TenantModel
{
    return TenantModel::query()->firstOrCreate(
        ['id' => 2],
        ['nombre' => 'Cordillera Andina S.A.', 'zona_horaria' => 'America/Guayaquil'],
    );
}

function actuarComoAdminDeTenant(int $tenantId = 1): UserModel
{
    if ($tenantId === 2) {
        tenantDos();
    }

    $admin = UserModel::factory()->admin()->create(['tenant_id' => $tenantId]);
    Sanctum::actingAs($admin, ['admin']);

    return $admin;
}

function actuarComoUsuarioDeTenant(int $tenantId = 1): UserModel
{
    if ($tenantId === 2) {
        tenantDos();
    }

    $usuario = UserModel::factory()->create(['tenant_id' => $tenantId]);
    Sanctum::actingAs($usuario, ['user']);

    return $usuario;
}

/**
 * Cuerpo válido mínimo (o con cambios) para crear una ficha.
 *
 * @param  array<string, mixed>  $cambios
 * @return array<string, mixed>
 */
function datosFicha(array $cambios = []): array
{
    return array_replace([
        'tipo_identificacion' => 'Cedula',
        'numero_identificacion' => '0102030405',
        'nombres' => 'Ana',
        'apellidos' => 'Núñez Paredes',
    ], $cambios);
}

/**
 * @param  array<string, mixed>  $cuerpo
 */
function crearFichaApi(mixed $prueba, array $cuerpo, ?string $clave = null): TestResponse
{
    return $prueba->postJson('/api/colaboradores', $cuerpo, ['Idempotency-Key' => $clave ?? (string) Str::uuid()]);
}

/**
 * @param  array<string, mixed>  $atributos
 */
function fichaDe(array $atributos = [], int $tenantId = 1): ColaboradorModel
{
    return ColaboradorModel::factory()->create(array_merge(['tenant_id' => $tenantId], $atributos));
}

/**
 * Sustituye el lector laboral por uno configurable.
 *
 * @param  array<string, Lectura>  $respuestas  Por nombre de método.
 */
function lectorLaboralFalso(mixed $prueba, array $respuestas): void
{
    app()->bind(LectorLaboralPort::class, fn () => new class($respuestas) implements LectorLaboralPort
    {
        /** @param array<string, Lectura> $r */
        public function __construct(private array $r) {}

        private function dar(string $metodo): Lectura
        {
            return $this->r[$metodo] ?? Lectura::noInstalado();
        }

        public function situacionVigente(int $tenantId, array $colaboradorIds, string $fecha): Lectura
        {
            return $this->dar('situacionVigente');
        }

        public function idsConVinculoVigente(int $tenantId, string $fecha): Lectura
        {
            return $this->dar('idsConVinculoVigente');
        }

        public function idsPorEstado(int $tenantId, string $estado, string $fecha): Lectura
        {
            return $this->dar('idsPorEstado');
        }

        public function colaboradoresEnPosiciones(int $tenantId, array $posicionIds, string $fecha): Lectura
        {
            return $this->dar('colaboradoresEnPosiciones');
        }

        public function trayectoria(int $tenantId, int $colaboradorId): Lectura
        {
            return $this->dar('trayectoria');
        }
    });
}

/**
 * @param  array<string, Lectura>  $respuestas  Por nombre de método.
 */
function lectorEstructuraFalso(mixed $prueba, array $respuestas): void
{
    app()->bind(LectorEstructuraPort::class, fn () => new class($respuestas) implements LectorEstructuraPort
    {
        /** @param array<string, Lectura> $r */
        public function __construct(private array $r) {}

        private function dar(string $metodo): Lectura
        {
            return $this->r[$metodo] ?? Lectura::noInstalado();
        }

        public function posiciones(array $posicionIds): Lectura
        {
            return $this->dar('posiciones');
        }

        public function posicionesPorPuesto(string $texto): Lectura
        {
            return $this->dar('posicionesPorPuesto');
        }

        public function posicionesPorUnidad(int $unidadId): Lectura
        {
            return $this->dar('posicionesPorUnidad');
        }

        public function unidades(): Lectura
        {
            return $this->dar('unidades');
        }
    });
}
