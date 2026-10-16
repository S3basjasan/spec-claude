<?php

namespace Tests\Support\Desarrollo;

use App\Modules\Desarrollo\Application\Ports\RelojInterface;
use DateTimeImmutable;

/** Reloj fijo para pruebas deterministas. */
class RelojFijo implements RelojInterface
{
    public function __construct(public string $fecha = '2026-06-15') {}

    public function hoy(int $usuarioId): DateTimeImmutable
    {
        return new DateTimeImmutable($this->fecha);
    }
}
