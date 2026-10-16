<?php

namespace App\Modules\Desarrollo\Application\Ports;

use DateTimeImmutable;

/** Fecha de hoy en la zona horaria de la organización del usuario (permite fijarla en las pruebas). */
interface RelojInterface
{
    public function hoy(int $usuarioId): DateTimeImmutable;
}
