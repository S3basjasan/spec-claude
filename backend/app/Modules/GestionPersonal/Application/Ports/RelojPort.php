<?php

namespace App\Modules\GestionPersonal\Application\Ports;

use DateTimeImmutable;

/** Reloj del servidor: cada operación lo lee una sola vez y usa ese instante para todas sus comprobaciones. */
interface RelojPort
{
    public function ahora(): DateTimeImmutable;
}
