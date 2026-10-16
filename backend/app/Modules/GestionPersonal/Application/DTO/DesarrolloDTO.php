<?php

namespace App\Modules\GestionPersonal\Application\DTO;

final class DesarrolloDTO
{
    /** @param array<string, mixed> $datos Representación `desarrollo` (ver contracts/api-desarrollo.md). */
    public function __construct(public readonly array $datos) {}
}
