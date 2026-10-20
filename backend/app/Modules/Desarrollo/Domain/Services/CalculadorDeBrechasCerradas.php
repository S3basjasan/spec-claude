<?php

namespace App\Modules\Desarrollo\Domain\Services;

use App\Modules\Desarrollo\Domain\ValueObjects\PeriodoReporte;
use DateTimeImmutable;

/**
 * Brechas de competencia a partir de las evaluaciones de las DNF. Por cada (colaborador, competencia) se recorren las
 * DNF en orden: una brecha se detecta en la primera DNF «ConBrecha» mientras no haya una abierta y se cierra en la
 * primera DNF posterior «Cumple» o «Supera». «SinEvaluacion» se ignora. Tras un cierre, un nuevo «ConBrecha» abre otra.
 */
final class CalculadorDeBrechasCerradas
{
    /**
     * @param  list<array{dnf_id: int, fecha: string, colaborador_id: int, competencia_id: int, estado: string}>  $evaluaciones  ordenadas por fecha y dnf_id
     * @return list<array{colaborador_id: int, competencia_id: int, detectada_en: string, cerrada_en: string|null}>
     */
    public function brechas(array $evaluaciones): array
    {
        $porPar = [];
        foreach ($evaluaciones as $e) {
            $porPar[$e['colaborador_id'].'|'.$e['competencia_id']][] = $e;
        }

        $brechas = [];
        foreach ($porPar as $historial) {
            usort($historial, fn (array $a, array $b) => [$a['fecha'], $a['dnf_id']] <=> [$b['fecha'], $b['dnf_id']]);
            $abierta = null;
            foreach ($historial as $e) {
                if ($e['estado'] === 'SinEvaluacion') {
                    continue;
                }
                if ($e['estado'] === 'ConBrecha' && $abierta === null) {
                    $abierta = $e;
                } elseif (in_array($e['estado'], ['Cumple', 'Supera'], true) && $abierta !== null) {
                    $brechas[] = [
                        'colaborador_id' => $abierta['colaborador_id'], 'competencia_id' => $abierta['competencia_id'],
                        'detectada_en' => $abierta['fecha'], 'cerrada_en' => $e['fecha'],
                    ];
                    $abierta = null;
                }
            }
            if ($abierta !== null) {
                $brechas[] = [
                    'colaborador_id' => $abierta['colaborador_id'], 'competencia_id' => $abierta['competencia_id'],
                    'detectada_en' => $abierta['fecha'], 'cerrada_en' => null,
                ];
            }
        }

        return $brechas;
    }

    /** @param  list<array{detectada_en: string, cerrada_en: string|null}>  $brechas */
    public function detectadasEn(array $brechas, PeriodoReporte $periodo): int
    {
        return count(array_filter($brechas, fn (array $b) => $this->dentro($periodo, $b['detectada_en'])));
    }

    /** @param  list<array{detectada_en: string, cerrada_en: string|null}>  $brechas */
    public function cerradasEn(array $brechas, PeriodoReporte $periodo): int
    {
        return count(array_filter($brechas, fn (array $b) => $b['cerrada_en'] !== null && $this->dentro($periodo, $b['cerrada_en'])));
    }

    /**
     * Brechas activas a una fecha: para cada colaborador, las competencias «ConBrecha» de su DNF más reciente hasta esa fecha.
     *
     * @param  list<array{dnf_id: int, fecha: string, colaborador_id: int, competencia_id: int, estado: string}>  $evaluaciones
     */
    public function activasAl(array $evaluaciones, DateTimeImmutable $fecha): int
    {
        $limite = $fecha->format('Y-m-d');
        $masReciente = [];
        foreach ($evaluaciones as $e) {
            if ($e['fecha'] > $limite) {
                continue;
            }
            $clave = [$e['fecha'], $e['dnf_id']];
            if (! isset($masReciente[$e['colaborador_id']]) || $clave > $masReciente[$e['colaborador_id']]) {
                $masReciente[$e['colaborador_id']] = $clave;
            }
        }

        $activas = 0;
        foreach ($evaluaciones as $e) {
            if ($e['estado'] === 'ConBrecha' && isset($masReciente[$e['colaborador_id']]) && $masReciente[$e['colaborador_id']] === [$e['fecha'], $e['dnf_id']]) {
                $activas++;
            }
        }

        return $activas;
    }

    /**
     * Serie mensual del período: brechas activas al cierre de cada mes y brechas cerradas en el mes.
     *
     * @param  list<array{dnf_id: int, fecha: string, colaborador_id: int, competencia_id: int, estado: string}>  $evaluaciones
     * @return list<array{mes: string, activas: int, cerradas: int}>
     */
    public function serieMensual(array $evaluaciones, PeriodoReporte $periodo): array
    {
        $brechas = $this->brechas($evaluaciones);
        $serie = [];
        $mes = $periodo->desde->modify('first day of this month');
        $ultimo = $periodo->hasta->modify('first day of this month');
        while ($mes <= $ultimo) {
            $finDeMes = $mes->modify('last day of this month');
            $serie[] = [
                'mes' => $mes->format('Y-m'),
                'activas' => $this->activasAl($evaluaciones, $finDeMes),
                'cerradas' => $this->cerradasEn($brechas, new PeriodoReporte($mes, $finDeMes)),
            ];
            $mes = $mes->modify('first day of next month');
        }

        return $serie;
    }

    /**
     * Evolución de las competencias de un colaborador: nivel en la primera DNF frente al de la más reciente. Con una sola
     * evaluación de la competencia solo se informa el nivel actual.
     *
     * @param  list<array{dnf_id: int, fecha: string, competencia_id: int, estado: string, nivel_requerido: string, nivel_actual: string|null}>  $evaluaciones  de un solo colaborador
     * @return list<array{competencia_id: int, nivel_primera: string|null, nivel_actual: string, nivel_requerido: string, solo_actual: bool}>
     */
    public function evolucion(array $evaluaciones): array
    {
        $porCompetencia = [];
        foreach ($evaluaciones as $e) {
            if ($e['nivel_actual'] !== null) {
                $porCompetencia[$e['competencia_id']][] = $e;
            }
        }

        $filas = [];
        foreach ($porCompetencia as $competenciaId => $historial) {
            usort($historial, fn (array $a, array $b) => [$a['fecha'], $a['dnf_id']] <=> [$b['fecha'], $b['dnf_id']]);
            $primera = $historial[0];
            $ultima = $historial[count($historial) - 1];
            $soloActual = count($historial) < 2;
            $filas[] = [
                'competencia_id' => (int) $competenciaId,
                'nivel_primera' => $soloActual ? null : $primera['nivel_actual'],
                'nivel_actual' => $ultima['nivel_actual'],
                'nivel_requerido' => $ultima['nivel_requerido'],
                'solo_actual' => $soloActual,
            ];
        }

        usort($filas, fn (array $a, array $b) => $a['competencia_id'] <=> $b['competencia_id']);

        return $filas;
    }

    private function dentro(PeriodoReporte $periodo, string $fecha): bool
    {
        return $fecha >= $periodo->desde->format('Y-m-d') && $fecha <= $periodo->hasta->format('Y-m-d');
    }
}
