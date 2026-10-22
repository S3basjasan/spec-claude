<?php

namespace App\Modules\GestionPersonal\Domain\Entities;

use DateTimeImmutable;

/** Borrador o versión publicada de una política. Las fechas son instantes en UTC. */
final class VersionDePolitica
{
    /**
     * @param  array<string, mixed>  $parametros
     * @param  array<string, mixed>|null  $condiciones
     */
    public function __construct(
        public readonly int $id,
        public readonly int $tenantId,
        public readonly int $politicaId,
        public readonly ?int $numero,
        public readonly string $descripcion,
        public readonly array $parametros,
        public readonly ?int $baseVersionId,
        public readonly int $version,
        public readonly int $createdBy,
        public readonly int $updatedBy,
        public readonly DateTimeImmutable $createdAt,
        public readonly DateTimeImmutable $updatedAt,
        public readonly ?DateTimeImmutable $descartadaEn = null,
        public readonly ?int $descartadaPor = null,
        public readonly ?DateTimeImmutable $publicadaEn = null,
        public readonly ?int $publicadaPor = null,
        public readonly ?DateTimeImmutable $inicioVigencia = null,
        public readonly ?string $motivoCambio = null,
        public readonly ?string $revisorNombre = null,
        public readonly ?string $revisionObservaciones = null,
        public readonly ?DateTimeImmutable $revisionEn = null,
        public readonly ?array $condiciones = null,
        public readonly ?string $condicionesHash = null,
        public readonly ?DateTimeImmutable $retiradaEn = null,
        public readonly ?int $retiradaPor = null,
        public readonly ?string $retiroMotivo = null,
    ) {}

    public function esBorradorActivo(): bool
    {
        return $this->publicadaEn === null && $this->descartadaEn === null;
    }

    public function estaPublicada(): bool
    {
        return $this->publicadaEn !== null;
    }
}
