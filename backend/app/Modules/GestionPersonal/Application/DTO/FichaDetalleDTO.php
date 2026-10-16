<?php

namespace App\Modules\GestionPersonal\Application\DTO;

final class FichaDetalleDTO
{
    /** @param array<string, mixed> $datos Representación del detalle (ver contracts/colaboradores-api.md). */
    public function __construct(public readonly array $datos, public readonly bool $creado = false) {}
}
