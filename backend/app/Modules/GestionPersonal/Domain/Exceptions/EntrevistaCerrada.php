<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

/** Entrevista completada o no realizada. */
final class EntrevistaCerrada extends RuntimeException
{
    public function __construct(string $message = 'La entrevista ya fue presentada o cerrada y no puede modificarse.')
    {
        parent::__construct($message);
    }
}
