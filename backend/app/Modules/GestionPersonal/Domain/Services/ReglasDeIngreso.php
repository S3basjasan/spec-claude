<?php

namespace App\Modules\GestionPersonal\Domain\Services;

use App\Modules\GestionPersonal\Domain\Entities\AsignacionLaboral;
use App\Modules\GestionPersonal\Domain\Entities\VinculoLaboral;

/**
 * Reglas puras de un ingreso: forma de los datos y superposiciones del nuevo periodo abierto `[fecha, ∞)`.
 */
final class ReglasDeIngreso
{
    public const FECHA_MINIMA = '1900-01-01';

    /**
     * @param  list<string>  $contratosValidos  Claves habilitadas.
     * @return array<string, list<string>> Errores por campo (vacío si es válido).
     */
    public function errores(mixed $fecha, mixed $contrato, mixed $posicionId, string $hoy, array $contratosValidos): array
    {
        $errores = [];

        if (! is_string($fecha) || ! $this->esFechaValida($fecha)) {
            $errores['fecha_ingreso'][] = 'La fecha de ingreso es obligatoria y debe tener el formato AAAA-MM-DD.';
        } elseif ($fecha < self::FECHA_MINIMA || $fecha > $this->sumarAnios($hoy, 10)) {
            $errores['fecha_ingreso'][] = 'La fecha de ingreso está fuera del rango permitido.';
        }

        if (! is_string($contrato) || $contrato === '') {
            $errores['tipo_contrato'][] = 'El tipo de contrato es obligatorio.';
        } elseif (! in_array($contrato, $contratosValidos, true)) {
            $errores['tipo_contrato'][] = 'El tipo de contrato no está habilitado.';
        }

        if (! is_int($posicionId) && ! (is_string($posicionId) && ctype_digit($posicionId))) {
            $errores['posicion_id'][] = 'La posición es obligatoria.';
        } elseif ((int) $posicionId < 1) {
            $errores['posicion_id'][] = 'La posición es obligatoria.';
        }

        return $errores;
    }

    /**
     * Primer vínculo de la ficha que se superpone con un ingreso desde `$fecha`; null si no hay ninguno.
     *
     * @param  list<VinculoLaboral>  $vinculos
     */
    public function vinculoEnConflicto(string $fecha, array $vinculos): ?VinculoLaboral
    {
        $nuevo = new PeriodoLaboral($fecha);

        foreach ($vinculos as $vinculo) {
            if ($vinculo->periodo()->superpone($nuevo)) {
                return $vinculo;
            }
        }

        return null;
    }

    /**
     * Primera asignación de la posición que se superpone con una ocupación desde `$fecha`; null si no hay ninguna.
     *
     * @param  list<AsignacionLaboral>  $asignaciones
     */
    public function asignacionEnConflicto(string $fecha, array $asignaciones): ?AsignacionLaboral
    {
        $nuevo = new PeriodoLaboral($fecha);

        foreach ($asignaciones as $asignacion) {
            if ($asignacion->periodo()->superpone($nuevo)) {
                return $asignacion;
            }
        }

        return null;
    }

    private function esFechaValida(string $fecha): bool
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fecha, $m)) {
            return false;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    private function sumarAnios(string $fecha, int $anios): string
    {
        return sprintf('%04d', (int) substr($fecha, 0, 4) + $anios).substr($fecha, 4);
    }
}
