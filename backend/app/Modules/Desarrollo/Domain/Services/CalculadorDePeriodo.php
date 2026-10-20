<?php

namespace App\Modules\Desarrollo\Domain\Services;

use App\Modules\Desarrollo\Domain\Exceptions\PeriodoInvalido;
use App\Modules\Desarrollo\Domain\ValueObjects\PeriodoReporte;
use App\Modules\Desarrollo\Domain\ValueObjects\VariacionIndicador;
use DateTimeImmutable;
use Throwable;

/** Resuelve el período de un reporte (año actual por defecto) y compara un valor con el del período anterior equivalente. */
final class CalculadorDePeriodo
{
    public function resolver(?string $desde, ?string $hasta, DateTimeImmutable $hoy): PeriodoReporte
    {
        if ($desde === null && $hasta === null) {
            return PeriodoReporte::anioDe($hoy);
        }

        $anio = PeriodoReporte::anioDe($hoy);
        try {
            return new PeriodoReporte(
                $desde === null ? $anio->desde : $this->fecha($desde),
                $hasta === null ? $anio->hasta : $this->fecha($hasta),
            );
        } catch (PeriodoInvalido $e) {
            throw $e;
        } catch (Throwable) {
            throw new PeriodoInvalido('Las fechas del rango no tienen un formato válido (AAAA-MM-DD).');
        }
    }

    public function pertenece(PeriodoReporte $periodo, DateTimeImmutable $fecha): bool
    {
        return $periodo->contiene($fecha);
    }

    /**
     * Sin valor actual o anterior no hay comparación; con anterior igual a 0 hay diferencia pero no variación porcentual.
     */
    public function variacion(?float $actual, ?float $anterior): VariacionIndicador
    {
        if ($actual === null || $anterior === null) {
            return new VariacionIndicador($actual, $anterior, null, null);
        }

        $diferencia = $actual - $anterior;

        return new VariacionIndicador($actual, $anterior, $diferencia, $anterior == 0.0 ? null : $diferencia / abs($anterior) * 100);
    }

    private function fecha(string $texto): DateTimeImmutable
    {
        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $texto);
        if ($fecha === false || $fecha->format('Y-m-d') !== $texto) {
            throw new PeriodoInvalido('Las fechas del rango no tienen un formato válido (AAAA-MM-DD).');
        }

        return $fecha;
    }
}
