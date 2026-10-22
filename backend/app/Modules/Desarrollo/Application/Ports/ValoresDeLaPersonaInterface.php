<?php

namespace App\Modules\Desarrollo\Application\Ports;

/**
 * Valor actual de un colaborador en cada competencia. Mientras Gestión de Personal no registre el nivel por
 * competencia, la fuente es el último nivel evaluado en la DNF.
 */
interface ValoresDeLaPersonaInterface
{
    /**
     * @return array<int, string> competencia_id => 'A'|'B'|'C'|'D' (solo las competencias con nivel registrado)
     */
    public function nivelesDeCompetencias(int $tenantId, int $colaboradorId): array;
}
