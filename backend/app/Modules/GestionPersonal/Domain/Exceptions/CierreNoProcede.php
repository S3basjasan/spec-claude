<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

/** La salida no es efectiva o hay tareas requeridas sin completar o con evidencia no disponible. */
final class CierreNoProcede extends RuntimeException
{
    /** @param list<string> $bloqueos */
    public function __construct(string $message = 'El cierre administrativo todavía no puede confirmarse.', public readonly array $bloqueos = [])
    {
        parent::__construct($message);
    }
}
