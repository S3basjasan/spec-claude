<?php

namespace App\Modules\GestionPersonal\Application\Services;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Domain\Entities\Expediente;
use App\Modules\GestionPersonal\Domain\Repositories\ExpedienteRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Services\CuestionarioDeSalida;
use App\Modules\GestionPersonal\Domain\ValueObjects\EstadoDeSalida;

/**
 * Arma el expediente para su destinatario. El del Administrador incluye tareas con evidencia, aplicación, cierre,
 * historial y acciones; el del Colaborador es otra forma, sin respaldos, historial ni incidencias.
 */
final class ArmadorDeExpediente
{
    public function __construct(
        private readonly ExpedienteRepositoryInterface $expedientes,
        private readonly EvaluadorDeCierre $cierre,
        private readonly ValidadorDeRespaldos $respaldos,
        private readonly CuestionarioDeSalida $cuestionario,
    ) {}

    /** @return array<string, mixed> */
    public function paraAdministrador(ContextoDeTenant $contexto, Expediente $e): array
    {
        $ficha = $this->expedientes->fichaResumen($e->tenantId, $e->colaboradorId);
        $nombres = $this->expedientes->nombresDeCuentas(array_values(array_filter([$e->formalizadoPor, $e->cierrePor])));
        $bloqueos = $this->cierre->bloqueos($e);
        $hoy = $contexto->hoy();
        $activo = $e->estado !== EstadoDeSalida::Cancelada;

        return [
            'id' => $e->id,
            'colaborador' => ['id' => $e->colaboradorId, 'codigo' => $ficha['codigo'] ?? null, 'nombre' => $ficha['nombre'] ?? null],
            'motivo' => ['clave' => $e->motivo->value, 'etiqueta' => $e->motivo->etiqueta()],
            'fecha_efectiva' => $e->fechaEfectiva,
            'estado' => $e->estado->value,
            'version' => $e->version,
            'formalizado' => ['por' => ['id' => $e->formalizadoPor, 'nombre' => $nombres[$e->formalizadoPor] ?? null], 'en' => $e->formalizadoEn],
            'politica' => ['version_id' => $e->politicaVersionId, 'numero' => $e->politicaNumero],
            'aplicacion' => [
                'aplicada_en' => $e->aplicadaEn,
                'por_sistema' => $e->aplicadaPorSistema,
                'intentos' => $e->intentosAplicacion,
                'incidencia' => $e->incidenciaCodigo === null ? null : ['codigo' => $e->incidenciaCodigo, 'detalle' => $e->incidenciaDetalle],
            ],
            'cuenta_estado' => $e->cuentaEstado,
            'cierre' => [
                'estado' => $e->cierreEstado,
                'por' => $e->cierrePor === null ? null : ['id' => $e->cierrePor, 'nombre' => $nombres[$e->cierrePor] ?? null],
                'en' => $e->cierreEn,
                'bloqueos' => $bloqueos,
            ],
            'cancelacion' => $e->cancelacionRazon === null ? null : ['razon' => $e->cancelacionRazon, 'en' => $e->cancelacionEn],
            'tareas' => array_map(fn (array $t) => [
                'id' => $t['id'], 'orden' => $t['orden'], 'tipo' => $t['tipo'], 'nombre' => $t['nombre'], 'descripcion' => $t['descripcion'],
                'exige_evidencia' => $t['exige_evidencia'], 'estado' => $t['estado'],
                'completada_por' => $t['completada_por'] === null ? null : ['id' => $t['completada_por'], 'nombre' => $this->expedientes->nombresDeCuentas([$t['completada_por']])[$t['completada_por']] ?? null],
                'completada_en' => $t['completada_en'],
                'respaldos' => $this->respaldos->estados($e->tenantId, $e->colaboradorId, $t['documento_ids']),
            ], $e->tareas),
            'respaldos' => $this->respaldos->estados($e->tenantId, $e->colaboradorId, $e->documentoIds),
            'entrevista' => [
                'estado' => $e->entrevista['estado']->value,
                'version' => $e->entrevista['version'],
                'presentada_en' => $e->entrevista['presentada_en'],
                'motivo_no_realizada' => $e->entrevista['motivo_no_realizada'],
                'no_realizada_en' => $e->entrevista['no_realizada_en'],
            ],
            'acciones' => [
                'cancelar' => $e->estado->esPendienteDeAplicar(),
                'aplicar' => $e->estado->esPendienteDeAplicar() && $e->fechaEfectiva <= $hoy,
                'completar_tareas' => $activo && ! $e->estaCerrado(),
                'cerrar' => $bloqueos === [],
                'no_realizada' => $activo && ! $e->estaCerrado() && ! $e->entrevista['estado']->estaResuelta(),
            ],
            'historial' => $this->expedientes->eventos($e->tenantId, $e->id),
        ];
    }

    /** @return array<string, mixed> */
    public function paraColaborador(Expediente $e): array
    {
        return [
            'id' => $e->id,
            'motivo' => ['clave' => $e->motivo->value, 'etiqueta' => $e->motivo->etiqueta()],
            'fecha_efectiva' => $e->fechaEfectiva,
            // Una incidencia de aplicación no se le muestra: la salida sigue figurando como programada.
            'estado' => $e->estado === EstadoDeSalida::Efectiva ? 'Efectiva' : 'Programada',
            'tareas' => array_map(fn (array $t) => [
                'orden' => $t['orden'], 'tipo' => $t['tipo'], 'nombre' => $t['nombre'], 'descripcion' => $t['descripcion'], 'estado' => $t['estado'],
            ], $e->tareas),
            'entrevista' => [
                'estado' => $e->entrevista['estado']->value,
                'version' => $e->entrevista['version'],
                'responder_disponible' => ! $e->entrevista['estado']->estaResuelta(),
                'cuestionario_version' => CuestionarioDeSalida::VERSION,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function entrevistaDelColaborador(Expediente $e): array
    {
        $filas = $this->expedientes->respuestasDe($e->tenantId, (int) $e->entrevista['id']);
        $valoraciones = [];
        $categorias = [];
        $abiertas = [];
        foreach ($filas as $f) {
            match ($f['tipo']) {
                'valoracion' => $valoraciones[$f['pregunta_id']] = $f['valor'],
                'categoria' => $categorias[] = $f['pregunta_id'],
                default => $abiertas[$f['pregunta_id']] = $f['texto'],
            };
        }

        return [
            'estado' => $e->entrevista['estado']->value,
            'version' => $e->entrevista['version'],
            'cerrada' => $e->entrevista['estado']->estaResuelta(),
            'presentada_en' => $e->entrevista['presentada_en'],
            'cuestionario' => $this->cuestionario->definicion(),
            'respuestas' => ['valoraciones' => (object) $valoraciones, 'categorias' => $categorias, 'abiertas' => (object) $abiertas],
        ];
    }
}
