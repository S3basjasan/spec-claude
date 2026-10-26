<?php

namespace App\Modules\GestionPersonal\Application\DTO;

use App\Modules\GestionPersonal\Domain\Entities\Actuacion;

/** Resultado de una operación sobre una actuación; `nueva` es falso cuando solo se devolvió una repetición idéntica. */
final class ResultadoDeActuacion
{
    public function __construct(public readonly Actuacion $actuacion, public readonly bool $nueva) {}
}
