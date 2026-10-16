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

    /** Asigna el plan solo si la DNF aún no tiene uno; devuelve si lo asignó. */
    public function asignarPlan(int $tenantId, int $dnfId, int $planId, int $actorId, DateTimeImmutable $momento): bool;
}
