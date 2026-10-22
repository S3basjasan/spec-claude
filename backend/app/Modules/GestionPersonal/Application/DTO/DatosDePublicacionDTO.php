<?php

namespace App\Modules\GestionPersonal\Application\DTO;

final class DatosDePublicacionDTO
{
    /** @param array<string, mixed> $inicio */
    public function __construct(
        public readonly int $version,
        public readonly string $motivo,
        public readonly string $revisor,
        public readonly string $observaciones,
        public readonly array $inicio,
        public readonly ?string $claveDeOperacion,
    ) {}
}
