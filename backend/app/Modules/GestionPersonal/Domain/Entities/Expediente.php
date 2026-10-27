<?php

namespace App\Modules\GestionPersonal\Domain\Entities;

use App\Modules\GestionPersonal\Domain\ValueObjects\EstadoDeEntrevista;
use App\Modules\GestionPersonal\Domain\ValueObjects\EstadoDeSalida;
use App\Modules\GestionPersonal\Domain\ValueObjects\MotivoDeSalida;

/** Expediente de offboarding con sus tareas y su entrevista. La política conservada no cambia después de formalizar. */
final class Expediente
{
    /**
     * @param  array<string, mixed>  $politicaCondiciones
     * @param  list<array{id: int, orden: int, tipo: string, nombre: string, descripcion: string|null, exige_evidencia: bool, estado: string, completada_por: int|null, completada_en: string|null, documento_ids: list<int>}>  $tareas
     * @param  array{id: int, estado: EstadoDeEntrevista, cuestionario_version: int, presentada_en: string|null, finalizada_en: string|null, motivo_no_realizada: string|null, no_realizada_por: int|null, no_realizada_en: string|null, version: int}  $entrevista
     * @param  list<int>  $documentoIds
     */
    public function __construct(
        public readonly int $id,
        public readonly int $tenantId,
        public readonly int $colaboradorId,
        public readonly int $vinculoId,
        public readonly MotivoDeSalida $motivo,
        public readonly string $fechaEfectiva,
        public readonly EstadoDeSalida $estado,
        public readonly string $cierreEstado,
        public readonly ?int $cierrePor,
        public readonly ?string $cierreEn,
        public readonly string $cuentaEstado,
        public readonly int $politicaVersionId,
        public readonly int $politicaNumero,
        public readonly array $politicaCondiciones,
        public readonly int $formalizadoPor,
        public readonly string $formalizadoEn,
        public readonly ?string $cancelacionRazon,
        public readonly ?string $cancelacionEn,
        public readonly ?string $aplicadaEn,
        public readonly bool $aplicadaPorSistema,
        public readonly ?string $incidenciaCodigo,
        public readonly ?string $incidenciaDetalle,
        public readonly int $intentosAplicacion,
        public readonly int $version,
        public readonly array $tareas,
        public readonly array $entrevista,
        public readonly array $documentoIds,
    ) {}

    public function estaCerrado(): bool
    {
        return $this->cierreEstado === 'Cerrado';
    }

    /** @return list<array<string, mixed>> Tareas requeridas aún sin completar. */
    public function tareasPendientes(): array
    {
        return array_values(array_filter($this->tareas, fn (array $t) => $t['estado'] !== 'Completada'));
    }

    /** @return array<string, mixed>|null */
    public function tarea(int $id): ?array
    {
        foreach ($this->tareas as $tarea) {
            if ($tarea['id'] === $id) {
                return $tarea;
            }
        }

        return null;
    }
}
