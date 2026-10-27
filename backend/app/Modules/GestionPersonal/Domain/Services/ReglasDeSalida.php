<?php

namespace App\Modules\GestionPersonal\Domain\Services;

use App\Modules\GestionPersonal\Domain\Exceptions\DatosInvalidos;
use App\Modules\GestionPersonal\Domain\Exceptions\FechaEfectivaInvalida;
use App\Modules\GestionPersonal\Domain\ValueObjects\MotivoDeSalida;

/** Reglas puras de la formalización de una salida. Las fechas son de calendario (AAAA-MM-DD) del tenant. */
final class ReglasDeSalida
{
    public const RAZON_MAXIMA = 1000;

    public const MAX_RESPALDOS = 10;

    /**
     * @throws DatosInvalidos
     * @throws FechaEfectivaInvalida
     */
    public function verificar(mixed $motivo, mixed $fechaEfectiva, string $hoy, string $inicioDelVinculo): MotivoDeSalida
    {
        $errores = [];
        $motivoValido = is_string($motivo) ? MotivoDeSalida::tryFrom($motivo) : null;
        if ($motivoValido === null) {
            $errores['motivo'][] = 'El motivo administrativo es obligatorio y debe ser uno del catálogo.';
        }
        if (! is_string($fechaEfectiva) || ! $this->esFecha($fechaEfectiva)) {
            $errores['fecha_efectiva'][] = 'La fecha efectiva es obligatoria y debe tener el formato AAAA-MM-DD.';
        }
        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }

        if ($fechaEfectiva < $hoy) {
            throw new FechaEfectivaInvalida('La fecha efectiva no puede ser anterior a hoy.');
        }
        if ($fechaEfectiva <= $inicioDelVinculo) {
            throw new FechaEfectivaInvalida('La fecha efectiva debe ser posterior al inicio del vínculo laboral.');
        }

        return $motivoValido;
    }

    private function esFecha(string $fecha): bool
    {
        return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fecha, $m) === 1 && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }
}
