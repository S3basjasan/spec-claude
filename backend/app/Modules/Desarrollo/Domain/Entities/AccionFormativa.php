<?php

namespace App\Modules\Desarrollo\Domain\Entities;

use App\Modules\Desarrollo\Domain\ValueObjects\TipoAccion;
use DateTimeImmutable;

/** Acción formativa de un plan. Salvo nombre y competencias, los datos pueden faltar mientras el plan es Borrador. */
final class AccionFormativa
{
    /**
     * @param  list<int>  $competenciaIds
     * @param  list<int>  $colaboradorIds
     */
    public function __construct(
        public readonly ?int $id,
        public readonly string $nombre,
        public readonly ?int $cursoId,
        public readonly ?TipoAccion $tipo,
        public readonly ?DateTimeImmutable $fechaInicio,
        public readonly ?DateTimeImmutable $fechaFin,
        public readonly ?float $duracionHoras,
        public readonly ?string $formador,
        public readonly ?int $capacidadMaxima,
        public readonly ?float $costo,
        public readonly bool $cancelada,
        public readonly ?string $motivoCancelacion,
        public readonly array $competenciaIds,
        public readonly array $colaboradorIds,
    ) {}

    public function inscritos(): int
    {
        return count($this->colaboradorIds);
    }

    public function tieneFechas(): bool
    {
        return $this->fechaInicio !== null && $this->fechaFin !== null;
    }
}
