<?php

namespace App\Modules\GestionPersonal\Application\Services;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\Ports\PosicionesAsignablesPort;
use App\Modules\GestionPersonal\Domain\Entities\Actuacion;
use App\Modules\GestionPersonal\Domain\Repositories\ActuacionRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Services\CatalogoDePoliticas;
use App\Modules\GestionPersonal\Domain\ValueObjects\EstadoDeActuacion;
use Throwable;

/**
 * Arma el detalle de una actuación para su destinatario. El del Administrador incluye comprobaciones, evaluación,
 * historial y acciones disponibles; el del Colaborador es otra forma, sin los campos restringidos (no se filtra en la
 * interfaz). Los datos de posiciones vienen de Estructura y no se reproducen como definiciones propias.
 */
final class ArmadorDeDetalleDeActuacion
{
    public function __construct(
        private readonly ActuacionRepositoryInterface $actuaciones,
        private readonly ComprobadorDeCondiciones $comprobador,
        private readonly PosicionesAsignablesPort $posiciones,
        private readonly ValidadorDeRespaldos $respaldos,
        private readonly CatalogoDePoliticas $catalogo,
    ) {}

    /** @return array<string, mixed> */
    public function paraAdministrador(ContextoDeTenant $contexto, Actuacion $a): array
    {
        $resoluble = $a->estado->esResoluble() && ! $a->tipo->esAusencia();
        $comprobacion = $resoluble ? $this->comprobador->evaluar($contexto, $a) : null;

        $ficha = $this->actuaciones->fichaResumen($contexto->tenantId, $a->colaboradorId);
        $nombres = $this->actuaciones->nombresDeCuentas([$a->presentadaPor]);
        $hoy = $contexto->hoy();

        return [
            'id' => $a->id,
            'tipo' => $a->tipo->value,
            'categoria' => $a->tipo->categoria(),
            'estado' => $a->estado->value,
            'version' => $a->version,
            'colaborador' => ['id' => $a->colaboradorId, 'codigo' => $ficha['codigo'] ?? null, 'nombre' => $ficha['nombre'] ?? null],
            'presentada' => [
                'en' => $a->presentadaEn,
                'fecha' => $a->fechaPresentacion,
                'por' => ['id' => $a->presentadaPor, 'nombre' => $nombres[$a->presentadaPor] ?? null],
                'en_nombre_de_colaborador' => $a->enNombreDeColaborador,
            ],
            'motivo' => $a->motivo,
            'periodo' => $a->novedad === null ? null : [
                'inicio' => $a->novedad['fecha_inicio'],
                'fin' => $a->novedad['fecha_fin'],
                'ocurrida' => $a->novedad['ocurrida'],
            ],
            'revision' => $a->novedad === null || $a->novedad['revision_observacion'] === null ? null : ['observacion' => $a->novedad['revision_observacion']],
            'cruce_reconocido' => $a->novedad === null || $a->novedad['cruce_reconocido_por'] === null ? null : [
                'por' => $a->novedad['cruce_reconocido_por'], 'en' => $a->novedad['cruce_reconocido_en'],
            ],
            'movimiento' => $a->movimiento === null ? null : $this->movimiento($a, $comprobacion),
            'politica' => [
                'version_id' => $a->politicaVersionId,
                'numero' => $a->politicaNumero,
                'tipo' => $a->tipo->politica()->value,
                'parametros' => $a->parametros(),
                'reglas_fijas' => $this->catalogo->reglasFijas($a->tipo->politica()),
            ],
            'comprobaciones' => $comprobacion['comprobaciones'] ?? [],
            'puede_aprobar' => $comprobacion['puede_aprobar'] ?? false,
            'motivo_no_aprobable' => $resoluble ? ($comprobacion['motivo_no_aprobable'] ?? null) : 'ESTADO_NO_RESOLUBLE',
            'cruces' => $comprobacion['cruces'] ?? [],
            'documentos' => $this->respaldos->estados($a->tenantId, $a->colaboradorId, $a->documentoIds),
            'acciones' => [
                'revisar' => $a->estado === EstadoDeActuacion::Presentada && ! $a->tipo->esAusencia(),
                'rechazar' => $resoluble,
                'aprobar' => $resoluble && ($comprobacion['puede_aprobar'] ?? false),
                'retirar' => $a->tipo->esSolicitudDeNovedad() && $a->estado->esResoluble(),
                'revisar_ausencia' => $a->tipo->esAusencia() && $a->estado === EstadoDeActuacion::Informada,
                'aplicar' => $a->tipo->esMovimiento() && $a->estado->esPendienteDeAplicar() && ($a->fechaEfectiva() ?? '9999-12-31') <= $hoy,
            ],
            'historial' => $this->actuaciones->historial($contexto->tenantId, $a->id),
        ];
    }

    /** @return array<string, mixed> */
    public function paraColaborador(ContextoDeTenant $contexto, Actuacion $a): array
    {
        if ($a->tipo->esMovimiento()) {
            return [
                'id' => $a->id,
                'tipo' => $a->tipo->value,
                'categoria' => 'Movimiento',
                'estado' => $this->estadoVisibleDeMovimiento($a->estado),
                'fecha_efectiva' => $a->fechaEfectiva(),
            ];
        }

        $parametros = $a->parametros();
        $resolucion = null;
        foreach (array_reverse($this->actuaciones->historial($contexto->tenantId, $a->id)) as $evento) {
            if (in_array($evento['evento'], ['Aprobada', 'Rechazada'], true)) {
                $resolucion = ['sentido' => $evento['evento'], 'justificacion' => $evento['justificacion'], 'resuelta_en' => $evento['en']];
                break;
            }
        }
        if ($a->tipo->esAusencia() && $a->novedad !== null && $a->novedad['revision_observacion'] !== null) {
            $resolucion = ['sentido' => $a->estado->value, 'justificacion' => $a->novedad['revision_observacion'], 'resuelta_en' => null];
        }

        return [
            'id' => $a->id,
            'tipo' => $a->tipo->value,
            'categoria' => 'Novedad',
            'estado' => $a->estado->value,
            'version' => $a->version,
            'presentada_en' => $a->presentadaEn,
            'motivo' => $a->motivo,
            'periodo' => ['inicio' => $a->novedad['fecha_inicio'] ?? null, 'fin' => $a->novedad['fecha_fin'] ?? null],
            'politica' => [
                'numero' => $a->politicaNumero,
                'requisitos' => array_intersect_key($parametros, array_flip(['justificacion_obligatoria', 'documento_obligatorio', 'anticipacion_dias'])),
            ],
            'resultado' => $resolucion,
            'retirable' => $a->tipo->esSolicitudDeNovedad() && $a->estado->esResoluble(),
        ];
    }

    /** Un movimiento aprobado con incidencia se muestra como aprobado pendiente de aplicar; nunca como aplicado. */
    public function estadoVisibleDeMovimiento(EstadoDeActuacion $estado): string
    {
        return match ($estado) {
            EstadoDeActuacion::Programada => 'Programada',
            EstadoDeActuacion::Aplicada => 'Aplicada',
            default => 'Aprobada',
        };
    }

    public function esVisibleParaColaborador(Actuacion $a): bool
    {
        if ($a->tipo->esMovimiento()) {
            return in_array($a->estado, [EstadoDeActuacion::Aprobada, EstadoDeActuacion::Programada, EstadoDeActuacion::IncidenciaDeAplicacion, EstadoDeActuacion::Aplicada], true);
        }
        if ($a->tipo->esAusencia()) {
            return in_array($a->estado, [EstadoDeActuacion::Revisada, EstadoDeActuacion::Justificada], true);
        }

        return true;
    }

    /**
     * @param  array<string, mixed>|null  $comprobacion
     * @return array<string, mixed>
     */
    private function movimiento(Actuacion $a, ?array $comprobacion): array
    {
        $m = $a->movimiento ?? [];
        $destino = $this->describir((int) $m['posicion_destino_id']);
        $origen = $comprobacion['origen'] ?? $this->describir((int) $m['posicion_origen_id']);

        $evaluacion = $comprobacion['evaluacion'] ?? null;
        $evaluacionVista = $evaluacion !== null
            ? ['estado' => $evaluacion['estado']->value, 'motivo' => $evaluacion['motivo'], 'contexto' => $evaluacion['contexto'], 'consultada_en' => null]
            : ($m['evaluacion_estado'] === null ? null : [
                'estado' => $m['evaluacion_estado'], 'motivo' => null, 'contexto' => $m['evaluacion_contexto'], 'consultada_en' => $m['evaluacion_consultada_en'],
            ]);

        return [
            'fecha_efectiva' => $m['fecha_efectiva'],
            'posicion_actual' => $origen === null ? null : ['id' => (int) $m['posicion_origen_id'], 'puesto' => $origen['puesto'], 'unidad' => $origen['unidad']],
            'posicion_destino' => ['id' => (int) $m['posicion_destino_id'], 'puesto' => $destino['puesto'] ?? null, 'unidad' => $destino['unidad'] ?? null],
            'evaluacion' => $evaluacionVista,
            'aplicacion' => [
                'estado' => $m['aplicacion_estado'],
                'aplicada_en' => $m['aplicada_en'],
                'por_sistema' => $m['aplicada_por_sistema'],
                'intentos' => $m['intentos_aplicacion'],
                'incidencia' => $m['incidencia_codigo'] === null ? null : ['codigo' => $m['incidencia_codigo'], 'detalle' => $m['incidencia_detalle']],
            ],
        ];
    }

    /** @return array{puesto: string, unidad: string|null}|null */
    private function describir(int $posicionId): ?array
    {
        try {
            $posicion = $this->posiciones->describir($posicionId);
        } catch (Throwable) {
            return null;
        }

        return $posicion === null ? null : ['puesto' => $posicion['puesto'], 'unidad' => $posicion['unidad']];
    }
}
