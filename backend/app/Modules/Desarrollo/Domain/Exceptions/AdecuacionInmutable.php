<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

use LogicException;

/** Una adecuación guardada no se edita ni se elimina (FR-018). */
final class AdecuacionInmutable extends LogicException
{
    public function __construct()
    {
        parent::__construct('Una adecuación guardada no se puede modificar ni eliminar.');
    }
}
