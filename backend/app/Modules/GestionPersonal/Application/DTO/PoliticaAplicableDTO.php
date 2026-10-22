<?php

namespace App\Modules\GestionPersonal\Application\DTO;

use App\Modules\GestionPersonal\Domain\ValueObjects\TipoDePolitica;
use DateTimeImmutable;

/** Versión de política aplicable a una solicitud: lo que el consumidor conserva. */
final class PoliticaAplicableDTO
{
    /**
     * @param  array<string, mixed>  $parametros
     * @param  array<string, mixed>  $condiciones  Representación sellada al publicar.
     */
    public function __construct(
        public readonly int $versionId,
        public readonly int $numero,
        public readonly TipoDePolitica $tipo,
        public readonly DateTimeImmutable $inicioVigencia,
        public readonly array $parametros,
        public readonly array $condiciones,
        public readonly string $hash,
    ) {}

    /** `disponible` explícito en los tipos que lo tienen; Offboarding siempre admite. */
    public function admiteSolicitudes(): bool
    {
        return $this->tipo->tieneDisponibilidad() ? ($this->parametros['disponible'] ?? false) === true : true;
    }
}
