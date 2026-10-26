<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PosicionModel;
use App\Modules\GestionPersonal\Application\Ports\EvaluacionDelDestinoPort;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\ColaboradorCuentaModel;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\ColaboradorModel;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\DocumentoColaboradorModel;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\PoliticaVersionModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

/*
|--------------------------------------------------------------------------
| Helpers de la feature 020 (movimientos y novedades de personal). Datos sintéticos.
|--------------------------------------------------------------------------
*/

const URL_MOVIMIENTOS = '/api/movimientos-personal';

const URL_MIS_SOLICITUDES = '/api/mis-solicitudes';

/** Máxima autoridad del tenant (única sin jefe): las demás posiciones de las pruebas cuelgan de ella. */
function raizDeEstructura(int $tenantId = 1): PosicionModel
{
    return PosicionModel::query()->where('tenant_id', $tenantId)->whereNull('posicion_jefe_id')->where('estado', '<>', 'Suprimida')->first()
        ?? posicionAsignable([], $tenantId);
}

/**
 * Posición asignable que respeta la regla de una sola máxima autoridad por tenant (cuelga de la raíz).
 *
 * @param  array<string, mixed>  $atributos
 */
function posicionDeMovimiento(array $atributos = [], int $tenantId = 1): PosicionModel
{
    return posicionAsignable(array_merge(['posicion_jefe_id' => raizDeEstructura($tenantId)->id], $atributos), $tenantId);
}

/**
 * Ficha con un vínculo vigente (y su asignación abierta) en una posición.
 *
 * @param  array<string, mixed>  $atributos  Atributos de la ficha.
 * @return array{ficha: ColaboradorModel, vinculo: int, posicion: PosicionModel}
 */
function fichaConVinculo(array $atributos = [], int $tenantId = 1, ?string $ingreso = null, ?PosicionModel $posicion = null): array
{
    $ficha = fichaDe(array_merge(['numero_identificacion' => 'M'.str_replace('.', '', uniqid('', true))], $atributos), $tenantId);
    $posicion ??= posicionDeMovimiento([], $tenantId);
    $vinculo = vinculoDe($ficha->id, $ingreso ?? hoyDelTenant(-200), null, $posicion->id, null, $tenantId);

    return ['ficha' => $ficha, 'vinculo' => $vinculo, 'posicion' => $posicion];
}

/**
 * Cuenta de Colaborador asociada a una ficha, autenticada en la prueba.
 *
 * @param  array<string, bool>  $permisos
 */
function actuarComoColaboradorDe(ColaboradorModel $ficha, array $permisos = ['puede_consultar_ficha' => true, 'puede_consultar_documentos' => true]): UserModel
{
    $existente = ColaboradorCuentaModel::query()->where('colaborador_id', $ficha->id)->first();
    if ($existente !== null) {
        $usuario = UserModel::query()->findOrFail($existente->user_id);
        Sanctum::actingAs($usuario, ['user']);

        return $usuario;
    }

    $usuario = actuarComoUsuarioDeTenant((int) $ficha->tenant_id);
    ColaboradorCuentaModel::query()->create(array_merge([
        'tenant_id' => $ficha->tenant_id, 'user_id' => $usuario->id, 'colaborador_id' => $ficha->id,
    ], $permisos));

    return $usuario;
}

/**
 * Política publicada y vigente de un tipo, con los parámetros por defecto más los indicados.
 *
 * @param  array<string, mixed>  $parametros
 */
function politicaVigente(string $tipo, array $parametros = [], int $tenantId = 1): PoliticaVersionModel
{
    return politicaPublicada($tipo, ['parametros' => array_merge(parametrosValidos($tipo), $parametros), 'tenant_id' => $tenantId]);
}

/**
 * Respaldo documental disponible de una ficha (solo metadatos).
 *
 * @param  array<string, mixed>  $atributos
 */
function respaldoDe(ColaboradorModel $ficha, array $atributos = []): DocumentoColaboradorModel
{
    return DocumentoColaboradorModel::factory()->create(array_merge([
        'tenant_id' => $ficha->tenant_id,
        'colaborador_id' => $ficha->id,
        'categoria_id' => categoriaActiva([], (int) $ficha->tenant_id)->id,
    ], $atributos));
}

/**
 * Sustituye el puerto de evaluación del puesto destino.
 *
 * @param  'valida'|'no_disponible'|'otro_puesto'|'sin_requisitos'|'vencida'|'otro_colaborador'|'cae'  $modo
 */
function evaluadorDeDestino(string $modo = 'valida'): void
{
    app()->bind(EvaluacionDelDestinoPort::class, fn () => new class($modo) implements EvaluacionDelDestinoPort
    {
        public function __construct(private string $modo) {}

        public function consultar(int $tenantId, int $colaboradorId, int $posicionDestinoId, DateTimeImmutable $instante): array
        {
            if ($this->modo === 'no_disponible') {
                return ['estado' => 'NoDisponible', 'contexto' => null];
            }
            if ($this->modo === 'cae') {
                throw new RuntimeException('evaluador caído');
            }

            $puestoId = (int) PosicionModel::query()->whereKey($posicionDestinoId)->value('puesto_id');
            $contexto = [
                'colaborador_id' => $this->modo === 'otro_colaborador' ? $colaboradorId + 999 : $colaboradorId,
                'puesto_id' => $this->modo === 'otro_puesto' ? $puestoId + 999 : $puestoId,
                'requisitos' => $this->modo === 'sin_requisitos' ? [] : ['Formación afín', 'Experiencia en el área'],
                'evaluada_en' => $instante->modify('-1 day')->format('Y-m-d\TH:i:s\Z'),
                'resultado' => 'Apto con brechas en gestión',
            ];
            if ($this->modo === 'vencida') {
                $contexto['vigente_hasta'] = $instante->modify('-1 hour')->format('Y-m-d\TH:i:s\Z');
            }

            return ['estado' => 'Valida', 'contexto' => $contexto];
        }
    });
}

/** @param  array<string, mixed>  $cuerpo */
function presentarNovedadApi(mixed $prueba, array $cuerpo, ?string $clave = null): TestResponse
{
    return $prueba->postJson(URL_MOVIMIENTOS.'/novedades', $cuerpo, ['Idempotency-Key' => $clave ?? (string) Str::uuid()]);
}

/** @param  array<string, mixed>  $cuerpo */
function informarAusenciaApi(mixed $prueba, array $cuerpo, ?string $clave = null): TestResponse
{
    return $prueba->postJson(URL_MOVIMIENTOS.'/ausencias', $cuerpo, ['Idempotency-Key' => $clave ?? (string) Str::uuid()]);
}

/** @param  array<string, mixed>  $cuerpo */
function proponerMovimientoApi(mixed $prueba, array $cuerpo, ?string $clave = null): TestResponse
{
    return $prueba->postJson(URL_MOVIMIENTOS.'/movimientos', $cuerpo, ['Idempotency-Key' => $clave ?? (string) Str::uuid()]);
}

/**
 * Acción sobre una actuación existente (`revision`, `aprobacion`, `rechazo`, `retiro`, `revision-de-ausencia`, `aplicacion`).
 *
 * @param  array<string, mixed>  $cuerpo
 */
function accionApi(mixed $prueba, int $id, string $accion, array $cuerpo = [], ?string $clave = null, string $base = URL_MOVIMIENTOS): TestResponse
{
    return $prueba->postJson("{$base}/{$id}/{$accion}", $cuerpo, ['Idempotency-Key' => $clave ?? (string) Str::uuid()]);
}

/**
 * Cuerpo válido de un Permiso futuro (dentro de la anticipación de la política por defecto).
 *
 * @param  array<string, mixed>  $cambios
 * @return array<string, mixed>
 */
function datosNovedad(int $colaboradorId, array $cambios = []): array
{
    return array_replace([
        'colaborador_id' => $colaboradorId,
        'tipo' => 'Permiso',
        'fecha_inicio' => hoyDelTenant(5),
        'fecha_fin' => hoyDelTenant(6),
        'motivo' => 'Trámite personal',
    ], $cambios);
}

/**
 * Propone un ascenso (Administrador autenticado) y devuelve sus piezas. Requiere la política de Ascensos.
 *
 * @param  array<string, mixed>  $cambios  Cambios al cuerpo de la propuesta.
 * @return array{id: int, ficha: ColaboradorModel, origen: PosicionModel, destino: PosicionModel, vinculo: int}
 */
function movimientoPresentado(mixed $prueba, array $cambios = [], ?ColaboradorModel $ficha = null, ?PosicionModel $destino = null, int $tenantId = 1): array
{
    $conjunto = $ficha === null ? fichaConVinculo([], $tenantId) : null;
    $ficha ??= $conjunto['ficha'];
    $origen = $conjunto['posicion'] ?? PosicionModel::query()->whereKey(DB::table('asignaciones_laborales')->where('vinculo_id', DB::table('vinculos_laborales')->where('colaborador_id', $ficha->id)->value('id'))->value('posicion_id'))->firstOrFail();
    $destino ??= posicionDeMovimiento([], $tenantId);

    $id = (int) proponerMovimientoApi($prueba, datosMovimiento($ficha->id, $destino->id, $cambios))->assertCreated()->json('data.id');

    return ['id' => $id, 'ficha' => $ficha, 'origen' => $origen, 'destino' => $destino, 'vinculo' => (int) DB::table('vinculos_laborales')->where('colaborador_id', $ficha->id)->value('id')];
}

/**
 * Propone y aprueba un ascenso con un evaluador válido de prueba.
 *
 * @param  array<string, mixed>  $cambios
 * @return array{id: int, ficha: ColaboradorModel, origen: PosicionModel, destino: PosicionModel, vinculo: int}
 */
function movimientoAprobado(mixed $prueba, array $cambios = [], ?ColaboradorModel $ficha = null, ?PosicionModel $destino = null, int $tenantId = 1): array
{
    $movimiento = movimientoPresentado($prueba, $cambios, $ficha, $destino, $tenantId);
    evaluadorDeDestino('valida');
    accionApi($prueba, $movimiento['id'], 'aprobacion', ['version' => 1, 'justificacion' => 'Cumple los requisitos del puesto'])->assertOk();

    return $movimiento;
}

/**
 * Cuerpo válido de una propuesta de movimiento.
 *
 * @param  array<string, mixed>  $cambios
 * @return array<string, mixed>
 */
function datosMovimiento(int $colaboradorId, int $destinoId, array $cambios = []): array
{
    return array_replace([
        'colaborador_id' => $colaboradorId,
        'tipo' => 'Ascenso',
        'posicion_destino_id' => $destinoId,
        'fecha_efectiva' => hoyDelTenant(30),
        'motivo' => 'Desempeño sostenido en el área',
    ], $cambios);
}
