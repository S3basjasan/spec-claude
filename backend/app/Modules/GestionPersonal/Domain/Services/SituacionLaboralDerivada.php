<?php

namespace App\Modules\GestionPersonal\Domain\Services;

use App\Modules\GestionPersonal\Domain\Entities\VinculoLaboral;
use App\Modules\GestionPersonal\Domain\ValueObjects\SituacionLaboral;

/**
 * Deriva el estado laboral de los vínculos para una fecha de calendario (la de hoy en la zona del tenant).
 */
final class SituacionLaboralDerivada
{
    /**
     * @param  list<VinculoLaboral>  $vinculos
     */
    public function paraFecha(array $vinculos, string $hoy): SituacionLaboral
    {
        $vigente = null;
        $proximo = null;
        $conSalidaEfectiva = false;

        foreach ($vinculos as $vinculo) {
            $periodo = $vinculo->periodo();

            if ($periodo->contiene($hoy)) {
                $vigente = $vinculo;
            } elseif ($vinculo->fechaIngreso > $hoy && ($proximo === null || $vinculo->fechaIngreso < $proximo->fechaIngreso)) {
                $proximo = $vinculo;
            }

            if ($periodo->terminoEnOAntesDe($hoy)) {
                $conSalidaEfectiva = true;
            }
        }

        if ($vigente !== null) {
            return new SituacionLaboral(SituacionLaboral::ACTIVO, $vigente, $proximo);
        }

        return new SituacionLaboral(
            $conSalidaEfectiva ? SituacionLaboral::DESVINCULADO : SituacionLaboral::SIN_VINCULO,
            null,
            $proximo,
        );
    }
}
