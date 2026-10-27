<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

/** Ya existe una salida viva para el mismo vínculo. */
final class SalidaYaFormalizada extends RuntimeException
{
    public function __construct(string $message = 'El vínculo ya tiene una salida formalizada.')
    {
        parent::__construct($message);
    }
}
