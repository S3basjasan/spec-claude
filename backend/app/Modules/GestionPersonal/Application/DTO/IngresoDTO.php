<?php

namespace App\Modules\GestionPersonal\Application\DTO;

final class IngresoDTO
{
    /** @param array<string, mixed> $datos {vinculo, asignacion, situacion} (ver contracts/ingresos-api.md) */
    public function __construct(public readonly array $datos, public readonly bool $creado = false) {}
}
