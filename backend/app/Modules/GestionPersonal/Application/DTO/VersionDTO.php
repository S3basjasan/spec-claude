<?php

namespace App\Modules\GestionPersonal\Application\DTO;

use App\Modules\GestionPersonal\Domain\Entities\VersionDePolitica;
use App\Modules\GestionPersonal\Domain\ValueObjects\EstadoDeVersion;

/** Versión con su estado calculado para el instante de la consulta. */
final class VersionDTO
{
    public function __construct(
        public readonly VersionDePolitica $version,
        public readonly EstadoDeVersion $estado,
        /** Inicio de vigencia en la zona de la organización (`AAAA-MM-DDTHH:mm`), si está publicada. */
        public readonly ?string $inicioLocal,
    ) {}
}
