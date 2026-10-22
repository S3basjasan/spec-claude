<?php

namespace App\Modules\GestionPersonal\Application\DTO;

use App\Modules\GestionPersonal\Domain\ValueObjects\TipoDePolitica;

final class PoliticaResumenDTO
{
    public function __construct(
        public readonly TipoDePolitica $tipo,
        /** `SinConfigurar` | `Vigente` | `NoAdmiteSolicitudes` */
        public readonly string $situacion,
        public readonly ?bool $disponible,
        public readonly ?VersionDTO $vigente,
        public readonly ?VersionDTO $programada,
        public readonly ?VersionDTO $borrador,
    ) {}
}
