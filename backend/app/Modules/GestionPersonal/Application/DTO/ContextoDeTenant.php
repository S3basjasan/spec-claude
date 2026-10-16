<?php

namespace App\Modules\GestionPersonal\Application\DTO;

use DateTimeImmutable;
use DateTimeZone;

final class ContextoDeTenant
{
    public function __construct(
        public readonly int $tenantId,
        public readonly string $nombre,
        public readonly string $zonaHoraria,
    ) {}

    /** Fecha de hoy (Y-m-d) en la zona horaria del tenant. */
    public function hoy(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone($this->zonaHoraria)))->format('Y-m-d');
    }
}
