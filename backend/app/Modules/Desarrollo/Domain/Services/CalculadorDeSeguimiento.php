<?php

namespace App\Modules\Desarrollo\Domain\Services;

use App\Modules\Desarrollo\Domain\ValueObjects\HitoSeguimiento;
use DateTimeImmutable;

/**
 * Resultados del seguimiento a 30, 60 y 90 días de una actividad: tasa de respuesta, promedio de aplicación y de apoyo
 * por hito, y la alerta de reingreso (el promedio de aplicación cae al menos 1 punto respecto del hito anterior, con al
 * menos 3 respuestas en ambos hitos).
 */
final class CalculadorDeSeguimiento
{
    public const MINIMO_DE_RESPUESTAS = 3;

    public const CAIDA_PARA_ALERTA = 1.0;

    /**
     * @param  int  $elegibles  colaboradores con asistencia Presente en la actividad
     * @param  list<array{hito: int, aplicacion: int, apoyo: int}>  $respuestas
     * @return array{hitos: list<array{hito: int, vencimiento: string, respuestas: int, tasa_respuesta: float|null, promedio_aplicacion: float|null, promedio_apoyo: float|null}>, alerta_reingreso: bool}
     */
    public function calcular(int $elegibles, array $respuestas, DateTimeImmutable $fechaFin): array
    {
        $hitos = [];
        $promedios = [];
        foreach (HitoSeguimiento::cases() as $hito) {
            $delHito = array_values(array_filter($respuestas, fn (array $r) => $r['hito'] === $hito->value));
            $n = count($delHito);
            $aplicacion = $n === 0 ? null : array_sum(array_column($delHito, 'aplicacion')) / $n;
            $apoyo = $n === 0 ? null : array_sum(array_column($delHito, 'apoyo')) / $n;
            $promedios[$hito->value] = ['n' => $n, 'aplicacion' => $aplicacion];
            $hitos[] = [
                'hito' => $hito->value,
                'vencimiento' => $hito->vencimiento($fechaFin)->format('Y-m-d'),
                'respuestas' => $n,
                'tasa_respuesta' => $elegibles > 0 ? (float) ($n / $elegibles * 100) : null,
                'promedio_aplicacion' => $aplicacion === null ? null : (float) $aplicacion,
                'promedio_apoyo' => $apoyo === null ? null : (float) $apoyo,
            ];
        }

        return ['hitos' => $hitos, 'alerta_reingreso' => $this->hayAlerta($promedios)];
    }

    /** @param  array<int, array{n: int, aplicacion: float|null}>  $promedios */
    private function hayAlerta(array $promedios): bool
    {
        foreach ([30 => 60, 60 => 90] as $anterior => $siguiente) {
            $a = $promedios[$anterior];
            $s = $promedios[$siguiente];
            if ($a['n'] >= self::MINIMO_DE_RESPUESTAS && $s['n'] >= self::MINIMO_DE_RESPUESTAS
                && ($a['aplicacion'] - $s['aplicacion']) >= self::CAIDA_PARA_ALERTA - 1e-9) {
                return true;
            }
        }

        return false;
    }
}
