<?php

namespace App\Modules\Desarrollo\Domain\Repositories;

use App\Modules\Desarrollo\Domain\Entities\Dnf;
use App\Modules\Desarrollo\Domain\ValueObjects\FiltroDnf;
use App\Modules\Desarrollo\Domain\ValueObjects\PaginaDeResumenes;
use DateTimeImmutable;

/**
 * Persistencia append-only de las DNF: solo se inserta y, una única vez por DNF, se asigna el plan generado.
 * No existen operaciones de actualización ni de eliminación.
 */
interface DnfRepositoryInterface
{
    /** Inserta la DNF con sus resultados y detalles en una transacción y le asigna id, código y fecha. */
    public function guardar(Dnf $dnf): Dnf;

    /** DNF del tenant; con `$colaboradorId` solo incluye el resultado de ese colaborador (nulo si no pertenece). */
    public function obtener(int $tenantId, int $id, ?int $colaboradorId = null): ?Dnf;

    public function listar(int $tenantId, FiltroDnf $filtro): PaginaDeResumenes;

    /** DNF más reciente (individual o masiva) que evaluó al colaborador, solo con el resultado de ese colaborador. */
    public function masRecienteDeColaborador(int $tenantId, int $colaboradorId): ?Dnf;

    /**
     * Estado y niveles de cada competencia evaluada en cada DNF (solo lectura, para Reportes), ordenados por fecha y id.
     * Filtros opcionales: fecha_desde y fecha_hasta (AAAA-MM-DD), colaborador_id, colaborador_ids.
     *
     * @param  array{fecha_desde?: string, fecha_hasta?: string, colaborador_id?: int, colaborador_ids?: list<int>}  $filtros
     * @return list<array{dnf_id: int, fecha: string, colaborador_id: int, competencia_id: int, estado: string, nivel_requerido: string, nivel_actual: string|null}>
     */
    public function evaluacionesDeCompetencias(int $tenantId, array $filtros): array;

    /** Asigna el plan solo si la DNF aún no tiene uno; devuelve si lo asignó. */
    public function asignarPlan(int $tenantId, int $dnfId, int $planId, int $actorId, DateTimeImmutable $momento): bool;
}
