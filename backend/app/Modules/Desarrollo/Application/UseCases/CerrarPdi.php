<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\RelojInterface;
use App\Modules\Desarrollo\Application\Services\ArmadorDeDetalleDePdi;
use App\Modules\Desarrollo\Application\Services\ResolvedorDeEstadoDeAcciones;
use App\Modules\Desarrollo\Domain\Entities\CompromisoDePdi;
use App\Modules\Desarrollo\Domain\Exceptions\AccionesDePdiAbiertas;
use App\Modules\Desarrollo\Domain\Exceptions\DatosInvalidosDePdi;
use App\Modules\Desarrollo\Domain\Exceptions\PdiNoEncontrado;
use App\Modules\Desarrollo\Domain\Exceptions\ResultadosIncompletos;
use App\Modules\Desarrollo\Domain\Exceptions\VersionDePdiDesfasada;
use App\Modules\Desarrollo\Domain\Repositories\RepositorioDePdiInterface;
use App\Modules\Desarrollo\Domain\Services\CalculadorDeResultadoDePdi;
use App\Modules\Desarrollo\Domain\Services\ReglasDeTransicionDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\ResultadoDeCompromiso;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDeHistorialPdi;
use DateTimeImmutable;

/**
 * Cierra un plan Acordado: exige que todas las acciones estén Cumplidas o Canceladas y un resultado por compromiso, calcula
 * el resultado del plan y congela el estado final de las acciones vinculadas (FR-032, FR-033, FR-020a, FR-036).
 */
final class CerrarPdi
{
    public function __construct(
        private readonly RepositorioDePdiInterface $repositorio,
        private readonly ReglasDeTransicionDePdi $reglas,
        private readonly ResolvedorDeEstadoDeAcciones $resolvedor,
        private readonly CalculadorDeResultadoDePdi $calculador,
        private readonly ArmadorDeDetalleDePdi $armador,
        private readonly RelojInterface $reloj,
    ) {}

    /**
     * @param  list<array{compromiso_id: int, resultado: string}>  $resultados
     * @return array{plan: array<string, mixed>, resultado: string, resultado_etiqueta: string, ofrecer_adecuacion: array{colaborador_id: int, puesto_objetivo_id: int}|null}
     */
    public function ejecutar(int $tenantId, int $actorId, int $id, array $resultados, ?string $conclusion, int $version): array
    {
        $pdi = $this->repositorio->obtener($tenantId, $id) ?? throw new PdiNoEncontrado;
        $this->reglas->exigirParaCerrar($pdi);
        if ($pdi->version !== $version) {
            throw new VersionDePdiDesfasada($pdi->version);
        }

        $ahora = new DateTimeImmutable;
        $estados = $this->resolvedor->resolver($pdi, $this->reloj->hoy($actorId));
        $abiertas = count(array_filter($estados, fn (array $e) => ! $e['estado']->esFinal()));
        if ($abiertas > 0) {
            throw new AccionesDePdiAbiertas($abiertas);
        }

        $porCompromiso = $this->resultadosPorCompromiso($pdi->compromisos, $resultados);
        $resultadoDelPlan = $this->calculador->calcular(array_values($porCompromiso));
        $conclusion = $conclusion === null || trim($conclusion) === '' ? null : trim($conclusion);

        $nuevo = $pdi->con([
            'estado' => EstadoDePdi::Cerrado,
            'resultado' => $resultadoDelPlan,
            'conclusion' => $conclusion,
            'cerradoAt' => $ahora,
            'cerradoBy' => $actorId,
            'version' => $pdi->version + 1,
        ]);

        $this->repositorio->transaccion(function () use ($nuevo, $pdi, $actorId, $porCompromiso, $estados, $resultadoDelPlan, $conclusion) {
            $this->repositorio->actualizarCabecera($nuevo, $pdi->version, $actorId);
            $this->repositorio->guardarResultadosDeCompromisos($porCompromiso);
            foreach ($pdi->acciones() as $accion) {
                if ($accion->estaVinculada()) {
                    $this->repositorio->actualizarAccion((int) $accion->id, $estados[(int) $accion->id]['estado'], true, $accion->planAccionId, $actorId);
                }
            }
            $this->repositorio->registrarHistorial($pdi->tenantId, (int) $pdi->id, TipoDeHistorialPdi::Estado, null, EstadoDePdi::Acordado->value, EstadoDePdi::Cerrado->value, null, $actorId);
            $this->repositorio->registrarHistorial($pdi->tenantId, (int) $pdi->id, TipoDeHistorialPdi::Cierre, null, null, $resultadoDelPlan->value, $conclusion, $actorId);
        });

        return [
            'plan' => $this->armador->detalle($this->repositorio->obtener($tenantId, $id), true, $actorId),
            'resultado' => $resultadoDelPlan->value,
            'resultado_etiqueta' => $resultadoDelPlan->etiqueta(),
            'ofrecer_adecuacion' => $pdi->tipo->requierePuestoObjetivo()
                ? ['colaborador_id' => $pdi->colaboradorId, 'puesto_objetivo_id' => (int) $pdi->puestoObjetivoId]
                : null,
        ];
    }

    /**
     * @param  list<CompromisoDePdi>  $compromisos
     * @param  list<array{compromiso_id: int, resultado: string}>  $resultados
     * @return array<int, ResultadoDeCompromiso> compromiso_id => resultado
     */
    private function resultadosPorCompromiso(array $compromisos, array $resultados): array
    {
        $ids = array_map(fn (CompromisoDePdi $c) => (int) $c->id, $compromisos);
        $porCompromiso = [];
        foreach ($resultados as $r) {
            $compromisoId = (int) $r['compromiso_id'];
            if (! in_array($compromisoId, $ids, true)) {
                throw DatosInvalidosDePdi::campo('resultados', 'Uno de los compromisos no pertenece al plan.');
            }
            $porCompromiso[$compromisoId] = ResultadoDeCompromiso::from($r['resultado']);
        }

        foreach ($ids as $id) {
            if (! isset($porCompromiso[$id])) {
                throw new ResultadosIncompletos;
            }
        }

        return $porCompromiso;
    }
}
