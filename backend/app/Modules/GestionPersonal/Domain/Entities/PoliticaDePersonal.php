<?php

namespace App\Modules\GestionPersonal\Domain\Entities;

use App\Modules\GestionPersonal\Domain\ValueObjects\TipoDePolitica;

/** Cabecera de un tipo de política dentro de un tenant: contador de numeración y punto de bloqueo. */
final class PoliticaDePersonal
{
    public function __construct(
        public readonly int $id,
        public readonly int $tenantId,
        public readonly TipoDePolitica $tipo,
        public readonly int $ultimoNumero,
        public readonly int $version,
    ) {}
}
