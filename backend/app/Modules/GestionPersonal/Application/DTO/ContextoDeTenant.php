<?php

namespace App\Modules\GestionPersonal\Application\DTO;

use Illuminate\Support\Carbon;

final class ContextoDeTenant
{
    public function __construct(
        public readonly int $tenantId,
        public readonly string $nombre,
        public readonly string $zonaHoraria,
    ) {}

    /** Fecha de hoy (Y-m-d) en la zona horaria del tenant (respeta el reloj fijado en las pruebas). */
    public function hoy(): string
    {
        return Carbon::now($this->zonaHoraria)->toDateString();
    }
}
