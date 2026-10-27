<?php

namespace App\Modules\GestionPersonal\Domain\Repositories;

use App\Modules\GestionPersonal\Domain\Entities\AsignacionLaboral;
use App\Modules\GestionPersonal\Domain\Entities\VinculoLaboral;
use App\Modules\GestionPersonal\Domain\Exceptions\ConflictoDeUnicidad;
use App\Modules\GestionPersonal\Domain\Exceptions\IncidenciaDeAplicacion;
use App\Modules\GestionPersonal\Domain\Exceptions\IncidenciaDeSalida;
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

    /**
     * Posición de la asignación laboral vigente en la fecha de cada ficha (inicio no posterior y fin nulo o posterior).
     * Las fichas sin asignación vigente no aparecen.
     *
     * @param  list<int>  $colaboradorIds
     * @return array<int, int> colaborador_id => posicion_id
     */
    public function posicionesVigentesDeFichas(int $tenantId, array $colaboradorIds, string $fecha): array;

    /**
     * Fichas con un vínculo laboral vigente en la fecha (ingreso no posterior y salida nula o posterior).
     *
     * @param  list<int>|null  $colaboradorIds  si se da, restringe el resultado a estas fichas
     * @return list<int>
     */
    public function fichasConVinculoVigente(int $tenantId, ?array $colaboradorIds, string $fecha): array;

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

    /**
     * Salida efectiva: cierra la asignación abierta y el vínculo en la fecha efectiva (fin excluido), liberando la
     * posición desde esa fecha. Debe llamarse dentro de `ejecutarBajoBloqueo`.
     *
     * @throws IncidenciaDeSalida El vínculo ya no es vigente o la asignación cambió.
     */
    public function cerrarVinculoEnFecha(int $tenantId, int $vinculoId, string $fechaEfectiva, ?int $actorId): void;

    /**
     * Hecho laboral de un movimiento: cierra la asignación de origen en la fecha efectiva (fin excluido) y abre la
     * nueva desde esa fecha con la referencia a su actuación. Debe llamarse dentro de `ejecutarBajoBloqueo`.
     *
     * @return int Identificador de la asignación nueva.
     *
     * @throws IncidenciaDeAplicacion El origen ya no es la asignación abierta del vínculo.
     * @throws PosicionNoDisponible
     * @throws VinculoSuperpuesto
     * @throws ConflictoDeUnicidad La actuación ya generó su asignación.
     */
    public function cerrarYAbrirAsignacion(
        int $tenantId,
        int $vinculoId,
        int $asignacionOrigenId,
        int $posicionDestinoId,
        string $fechaEfectiva,
        int $actuacionId,
        ?int $actorId,
    ): int;
}
