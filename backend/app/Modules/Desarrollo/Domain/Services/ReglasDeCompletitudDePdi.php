<?php

namespace App\Modules\Desarrollo\Domain\Services;

use App\Modules\Desarrollo\Domain\Entities\Pdi;

/** Requisitos para confirmar el acuerdo del responsable (FR-026). */
final class ReglasDeCompletitudDePdi
{
    /**
     * @return list<string> sin_compromisos | compromiso_sin_acciones | sin_responsable | responsable_sin_permiso (vacía si está completo)
     */
    public function faltantes(Pdi $pdi, bool $responsableEsAdministrador): array
    {
        $faltantes = [];

        if ($pdi->compromisos === []) {
            $faltantes[] = 'sin_compromisos';
        } else {
            foreach ($pdi->compromisos as $compromiso) {
                if ($compromiso->acciones === []) {
                    $faltantes[] = 'compromiso_sin_acciones';
                    break;
                }
            }
        }

        if ($pdi->responsableId <= 0) {
            $faltantes[] = 'sin_responsable';
        } elseif (! $responsableEsAdministrador) {
            $faltantes[] = 'responsable_sin_permiso';
        }

        return $faltantes;
    }
}
