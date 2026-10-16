<?php

namespace App\Modules\GestionPersonal\Domain\Repositories;

use App\Modules\GestionPersonal\Domain\Entities\AsignacionLaboral;
use App\Modules\GestionPersonal\Domain\Entities\VinculoLaboral;
use App\Modules\GestionPersonal\Domain\Exceptions\IngresoNoVerificable;
use App\Modules\GestionPersonal\Domain\Exceptions\PosicionNoDisponible;
use App\Modules\GestionPersonal\Domain\Exceptions\VinculoSuperpuesto;

interface VinculoLaboralRepositoryInterface
{
    /**
     * Vínculos de una ficha con sus asignaciones, por fecha de ingreso ascendente.
     *
     * @return list<VinculoLaboral>
     */
    public function vinculosDeFicha(int $tenantId, int $colaboradorId): array;

    /**
     * @param  list<int>  $colaboradorIds
     * @return array<int, list<VinculoLaboral>> colaborador_id => vínculos
     */
    public function vinculosDeFichas(int $tenantId, array $colaboradorIds): array;

    /**
     * Vínculos de todas las fichas del tenant, agrupados por ficha (solo las que tienen alguno).
     *
     * @return array<int, list<VinculoLaboral>>
     */
    public function vinculosDelTenant(int $tenantId): array;

    /**
     * Asignaciones de la posición que no terminaron antes de `$desde` (fin nulo o posterior).
     *
     * @return list<AsignacionLaboral>
     */
    public function asignacionesDePosicionDesde(int $posicionId, string $desde): array;

    /**
     * Posiciones, entre las dadas, con una asignación vigente o futura en la fecha.
     *
     * @param  list<int>  $posicionIds
     * @return list<int>
     */
    public function posicionesConAsignacionVigenteOFutura(array $posicionIds, string $fecha): array;

    /**
     * @param  list<int>  $posicionIds
     * @return list<int> Fichas con una asignación vigente en la fecha en alguna de esas posiciones.
     */
    public function fichasEnPosiciones(int $tenantId, array $posicionIds, string $fecha): array;

    /** @return array{vinculo: VinculoLaboral, hash: string, actor: int}|null */
    public function porOperacion(int $tenantId, string $operacionId): ?array;

    /**
     * Ejecuta `$accion` en una transacción cuya primera instrucción toma el bloqueo de escritura sobre la ficha.
     * Reintenta de forma acotada ante bloqueo de la base.
     *
     * @template T
     *
     * @param  callable(): T  $accion
     * @return T
     *
     * @throws IngresoNoVerificable
     */
    public function ejecutarBajoBloqueo(int $tenantId, int $colaboradorId, callable $accion): mixed;

    /**
     * Inserta vínculo y asignación inicial (debe llamarse dentro de `ejecutarBajoBloqueo`).
     *
     * @throws VinculoSuperpuesto
     * @throws PosicionNoDisponible
     */
    public function insertarIngreso(
        int $tenantId,
        int $colaboradorId,
        string $operacionId,
        string $hash,
        string $fechaIngreso,
        string $tipoContratoClave,
        int $posicionId,
        int $actorId,
    ): VinculoLaboral;
}
