<?php

namespace App\Modules\Desarrollo\Domain\Entities;

use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\PeriodoDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\ResultadoDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDePdi;
use DateTimeImmutable;
use InvalidArgumentException;

/** Raíz de un plan de desarrollo individual con sus compromisos, acciones e hitos. */
final class Pdi
{
    /**
     * @param  list<CompromisoDePdi>  $compromisos
     * @param  list<HitoDePdi>  $hitos
     */
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly ?string $codigo,
        public readonly int $colaboradorId,
        public readonly TipoDePdi $tipo,
        public readonly string $objetivo,
        public readonly ?int $puestoObjetivoId,
        public readonly ?int $origenDnfId,
        public readonly ?int $origenAdecuacionId,
        public readonly int $responsableId,
        public readonly PeriodoDePdi $periodo,
        public readonly EstadoDePdi $estado,
        public readonly int $version,
        public readonly ?DateTimeImmutable $acuerdoResponsableAt,
        public readonly ?int $acuerdoResponsableBy,
        public readonly ?DateTimeImmutable $aceptacionColaboradorAt,
        public readonly ?string $comentarioColaborador,
        public readonly ?ResultadoDePdi $resultado,
        public readonly ?string $conclusion,
        public readonly ?DateTimeImmutable $cerradoAt,
        public readonly ?int $cerradoBy,
        public readonly int $createdBy,
        public readonly ?DateTimeImmutable $createdAt,
        public readonly array $compromisos = [],
        public readonly array $hitos = [],
    ) {
        if ($tipo->requierePuestoObjetivo() && $puestoObjetivoId === null) {
            throw new InvalidArgumentException('El puesto objetivo es obligatorio en los planes de Carrera y Talento.');
        }
        if (! $tipo->requierePuestoObjetivo() && $puestoObjetivoId !== null) {
            throw new InvalidArgumentException('El plan de Mejora no tiene puesto objetivo.');
        }
    }

    /**
     * Copia con los campos indicados reemplazados (los demás se conservan).
     *
     * @param  array<string, mixed>  $cambios  nombre del argumento del constructor => valor
     */
    public function con(array $cambios): self
    {
        return new self(...array_merge(get_object_vars($this), $cambios));
    }

    public function tieneAcuerdoDelResponsable(): bool
    {
        return $this->acuerdoResponsableAt !== null;
    }

    /** El colaborador solo ve Cerrados, Acordados y Borradores con el acuerdo del responsable (FR-047). */
    public function visibleParaColaborador(): bool
    {
        return $this->estado !== EstadoDePdi::Borrador || $this->tieneAcuerdoDelResponsable();
    }

    /** @return list<AccionDePdi> */
    public function acciones(): array
    {
        $acciones = [];
        foreach ($this->compromisos as $compromiso) {
            foreach ($compromiso->acciones as $accion) {
                $acciones[] = $accion;
            }
        }

        return $acciones;
    }
}
