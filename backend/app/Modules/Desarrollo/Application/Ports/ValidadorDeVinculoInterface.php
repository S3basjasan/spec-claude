<?php

namespace App\Modules\Desarrollo\Application\Ports;

use App\Modules\Desarrollo\Domain\Exceptions\VinculoInvalido;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDeAccionPdi;

/** Valida que una acción del Plan de Formación se pueda vincular a una acción del PDI (FR-018, FR-019). */
interface ValidadorDeVinculoInterface
{
    /** @throws VinculoInvalido */
    public function validar(int $tenantId, int $colaboradorId, TipoDeAccionPdi $tipo, int $planAccionId): void;
}
