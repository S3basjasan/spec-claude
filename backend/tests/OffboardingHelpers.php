<?php

use App\Modules\GestionPersonal\Application\Ports\CoordinadorDeCuentaPort;
use App\Modules\GestionPersonal\Application\Ports\PermisoDeEntrevistasPort;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\ColaboradorModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/*
|--------------------------------------------------------------------------
| Helpers de la feature 021 (offboarding de colaboradores). Datos sintéticos.
|--------------------------------------------------------------------------
*/

const URL_OFFBOARDING = '/api/offboarding';

const URL_MI_DESVINCULACION = '/api/mi-desvinculacion';

/**
 * Cuerpo válido de una formalización (fecha efectiva futura).
 *
 * @param  array<string, mixed>  $cambios
 * @return array<string, mixed>
 */
function datosSalida(int $colaboradorId, array $cambios = []): array
{
    return array_replace([
        'colaborador_id' => $colaboradorId,
        'motivo' => 'Renuncia',
        'fecha_efectiva' => hoyDelTenant(10),
        'documento_ids' => [],
    ], $cambios);
}

/** @param  array<string, mixed>  $cuerpo */
function formalizarSalidaApi(mixed $prueba, array $cuerpo, ?string $clave = null): TestResponse
{
    return $prueba->postJson(URL_OFFBOARDING, $cuerpo, ['Idempotency-Key' => $clave ?? (string) Str::uuid()]);
}

/**
 * Acción sobre un expediente (`cancelacion`, `aplicacion`, `cierre`, `tareas/{id}/completar`, `entrevista/no-realizada`).
 *
 * @param  array<string, mixed>  $cuerpo
 */
function accionSalidaApi(mixed $prueba, int $id, string $accion, array $cuerpo = [], ?string $clave = null): TestResponse
{
    return $prueba->postJson(URL_OFFBOARDING."/{$id}/{$accion}", $cuerpo, ['Idempotency-Key' => $clave ?? (string) Str::uuid()]);
}

/**
 * Salida formalizada por el Administrador del tenant (queda autenticado como tal). Requiere que la prueba haya
 * publicado antes la política de Offboarding (`politicaVigente('Offboarding')`).
 *
 * @param  array<string, mixed>  $cambios  Cambios al cuerpo de la formalización.
 * @return array{id: int, ficha: ColaboradorModel, vinculo: int, tareas: list<array<string, mixed>>}
 */
function salidaFormalizada(mixed $prueba, array $cambios = [], ?ColaboradorModel $ficha = null, int $tenantId = 1): array
{
    actuarComoAdminDeTenant($tenantId);
    $ficha ??= fichaConVinculo([], $tenantId)['ficha'];

    $respuesta = formalizarSalidaApi($prueba, datosSalida($ficha->id, $cambios))->assertCreated();

    return [
        'id' => (int) $respuesta->json('data.id'),
        'ficha' => $ficha,
        'vinculo' => (int) DB::table('vinculos_laborales')->where('colaborador_id', $ficha->id)->value('id'),
        'tareas' => $respuesta->json('data.tareas'),
    ];
}

/** Completa todas las tareas de la plantilla (con un respaldo cuando lo exigen) y deja la entrevista como «no realizada». */
function completarChecklist(mixed $prueba, array $salida): void
{
    $detalle = $prueba->getJson(URL_OFFBOARDING.'/'.$salida['id'])->assertOk()->json('data');
    foreach ($detalle['tareas'] as $tarea) {
        if ($tarea['tipo'] !== 'Plantilla') {
            continue;
        }
        $version = (int) $prueba->getJson(URL_OFFBOARDING.'/'.$salida['id'])->json('data.version');
        $cuerpo = ['version' => $version, 'documento_ids' => $tarea['exige_evidencia'] ? [respaldoDe($salida['ficha'])->id] : []];
        accionSalidaApi($prueba, $salida['id'], "tareas/{$tarea['id']}/completar", $cuerpo)->assertOk();
    }
}

/** Respuestas válidas de ejemplo para guardar la entrevista. @return array<string, mixed> */
function respuestasDeEntrevista(int $version = 1): array
{
    return [
        'version' => $version,
        'valoraciones' => ['DIM-DESARROLLO' => 2, 'DIM-SUPERVISION' => 4, 'DIM-GENERAL' => 3],
        'categorias' => ['CAT-DESARROLLO', 'CAT-COMPENSACION'],
        'abiertas' => ['ABI-01' => 'Busqué crecer y no había una ruta clara.', 'ABI-06' => 'Gracias por el apoyo.'],
    ];
}

/** Sustituye el puerto que coordina la cuenta con Identidad y Acceso. @param  'coordinada'|'pendiente'|'falla'  $modo */
function coordinadorDeCuenta(string $modo): void
{
    app()->bind(CoordinadorDeCuentaPort::class, fn () => new class($modo) implements CoordinadorDeCuentaPort
    {
        public function __construct(private string $modo) {}

        public function solicitar(int $tenantId, int $colaboradorId, string $fechaEfectiva): string
        {
            if ($this->modo === 'falla') {
                throw new RuntimeException('Identidad y Acceso no responde');
            }

            return $this->modo === 'coordinada' ? 'Coordinada' : 'Pendiente';
        }
    });
}

/** Concede o niega el permiso específico de consultar respuestas de entrevistas. */
function permisoDeEntrevistas(bool $puede): void
{
    app()->bind(PermisoDeEntrevistasPort::class, fn () => new class($puede) implements PermisoDeEntrevistasPort
    {
        public function __construct(private bool $puede) {}

        public function puede(int $userId, int $tenantId): bool
        {
            return $this->puede;
        }
    });
}
