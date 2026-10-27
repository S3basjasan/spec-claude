<?php

namespace App\Modules\GestionPersonal\Application\DTO;

use App\Modules\GestionPersonal\Domain\Entities\Expediente;

/** Resultado de una operación sobre un expediente; `nueva` es falso cuando solo se devolvió una repetición idéntica. */
final class ResultadoDeSalida
{
    public function __construct(public readonly Expediente $expediente, public readonly bool $nueva) {}
}
