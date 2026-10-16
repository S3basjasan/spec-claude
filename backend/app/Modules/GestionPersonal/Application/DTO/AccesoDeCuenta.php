<?php

namespace App\Modules\GestionPersonal\Application\DTO;

final class AccesoDeCuenta
{
    public function __construct(
        public readonly int $tenantId,
        public readonly int $colaboradorId,
        public readonly bool $puedeConsultarFicha,
        public readonly bool $puedeConsultarDocumentos,
        public readonly bool $puedeExportar,
    ) {}
}
