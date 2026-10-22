<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Services\ArmadorDeAdecuaciones;
use App\Modules\Desarrollo\Application\Services\ArmadorDeDiagnostico;
use App\Modules\Desarrollo\Domain\Entities\Adecuacion;
use App\Modules\Desarrollo\Domain\Entities\ResultadoDeAdecuacion;
use App\Modules\Desarrollo\Domain\Exceptions\ObjetivoCambio;
use App\Modules\Desarrollo\Domain\Exceptions\TiempoDeCierreInvalido;
use App\Modules\Desarrollo\Domain\Repositories\AdecuacionesRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\CalculadorDePreparacion;

/**
 * Guarda una adecuación. El servidor recalcula todo (no acepta valores, diferencias, estados ni preparación del
 * cliente); del cliente solo toma el tiempo estimado de cierre de cada brecha y las observaciones.
 */
final class GenerarAdecuacion
{
    public function __construct(
        private readonly ArmadorDeDiagnostico $diagnostico,
        private readonly ArmadorDeAdecuaciones $armador,
        private readonly AdecuacionesRepositoryInterface $adecuaciones,
        private readonly CalculadorDePreparacion $preparacion,
    ) {}

    /**
     * @param  array{colaborador_id: int, inscripcion_id: int|null, puesto_objetivo_id: int|null, experiencia_requerida_anios: float|null, puesto_objetivo_esperado_id: int|null, tiempos_cierre: list<array{dimension: string, elemento_id: int|null, meses: mixed}>, observaciones: string|null}  $datos
     * @return array<string, mixed>
     */
    public function ejecutar(int $tenantId, int $actorId, array $datos): array
    {
        $d = $this->diagnostico->armar(
            $tenantId, $datos['colaborador_id'], $datos['inscripcion_id'], $datos['puesto_objetivo_id'], $datos['experiencia_requerida_anios'],
        );

        if ($datos['puesto_objetivo_esperado_id'] !== null && $datos['puesto_objetivo_esperado_id'] !== $d->puestoObjetivoId) {
            throw new ObjetivoCambio;
        }

        $resultados = $this->conTiempos($d->resultados, $datos['tiempos_cierre']);
        $parametros = $this->adecuaciones->obtenerParametros($tenantId);
        $observaciones = $datos['observaciones'] === null || trim($datos['observaciones']) === '' ? null : trim($datos['observaciones']);

        $guardada = $this->adecuaciones->guardar(new Adecuacion(
            null, $tenantId, null, $d->colaboradorId, $d->puestoActualId, $d->puestoObjetivoId, $d->inscripcionId, $d->nivelObjetivoId,
            $d->experienciaRequeridaAnios, $this->preparacion->calcular($resultados, $parametros->umbralMeses), $parametros->umbralMeses,
            $observaciones, $actorId, null, $resultados,
        ));

        return $this->armador->adecuacion($guardada, true, null, null, $tenantId);
    }

    /**
     * @param  list<ResultadoDeAdecuacion>  $resultados
     * @param  list<array{dimension: string, elemento_id: int|null, meses: mixed}>  $tiempos
     * @return list<ResultadoDeAdecuacion>
     */
    private function conTiempos(array $resultados, array $tiempos): array
    {
        $porClave = [];
        foreach ($tiempos as $t) {
            $porClave[$t['dimension'].':'.($t['elemento_id'] ?? 0)] = $t['meses'];
        }

        $conocidas = [];
        $faltantes = [];
        $salida = [];
        foreach ($resultados as $r) {
            $clave = $r->clave();
            $conocidas[$clave] = true;
            $meses = $porClave[$clave] ?? null;

            if (! $r->esBrecha()) {
                if ($meses !== null) {
                    throw TiempoDeCierreInvalido::noEsBrecha($clave);
                }
                $salida[] = $r;

                continue;
            }
            if ($meses === null || $meses === '') {
                $faltantes[] = $clave;
                $salida[] = $r;

                continue;
            }
            if (! is_int($meses) || $meses < 1 || $meses > TiempoDeCierreInvalido::MAXIMO_MESES) {
                throw TiempoDeCierreInvalido::fueraDeRango($clave);
            }
            $salida[] = $r->conTiempoDeCierre($meses);
        }

        foreach (array_keys($porClave) as $clave) {
            if (! isset($conocidas[$clave])) {
                throw TiempoDeCierreInvalido::noEsBrecha($clave);
            }
        }
        if ($faltantes !== []) {
            throw TiempoDeCierreInvalido::faltante($faltantes);
        }

        return $salida;
    }
}
