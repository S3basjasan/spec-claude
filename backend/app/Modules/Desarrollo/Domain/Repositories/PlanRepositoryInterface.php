<?php

namespace App\Modules\Desarrollo\Domain\Repositories;

use App\Modules\Desarrollo\Domain\Entities\Plan;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoPlan;
use App\Modules\Desarrollo\Domain\ValueObjects\FiltroAcciones;
use App\Modules\Desarrollo\Domain\ValueObjects\FiltroPlanes;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoHistorialPlan;
use DateTimeImmutable;

/**
 * Persistencia de los planes de formación. Todas las consultas se acotan al tenant. Nunca elimina planes; solo
 * elimina acciones al reemplazar un Borrador.
 */
interface PlanRepositoryInterface
{
    /**
     * Ejecuta la acción en una transacción.
     *
     * @template T
     *
     * @param  callable(): T  $accion
     * @return T
     */
    public function transaccion(callable $accion): mixed;

    /** Crea el plan con el siguiente número del tenant y sus acciones, competencias y participantes. */
    public function crear(Plan $plan): Plan;

    /** Reemplaza los datos generales y las acciones de un plan existente (las ausentes se eliminan). */
    public function reemplazar(Plan $plan): Plan;

    public function obtener(int $tenantId, int $id): ?Plan;

    public function obtenerPorAccion(int $tenantId, int $accionId): ?Plan;

    public function existeNombre(int $tenantId, int $anio, string $nombreNormalizado, ?int $excluirPlanId = null): bool;

    /**
     * @return array{items: list<array{plan: Plan, cantidad_acciones: int, costo_total: float}>, total: int}
     */
    public function listar(int $tenantId, FiltroPlanes $filtro): array;

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function listarAcciones(int $tenantId, FiltroAcciones $filtro): array;

    /** Cambia el estado solo si sigue siendo el esperado; devuelve false si cambió mientras tanto. */
    public function cambiarEstadoSi(int $tenantId, int $planId, EstadoPlan $esperado, EstadoPlan $nuevo, ?DateTimeImmutable $fechaAprobacion, int $usuarioId): bool;

    public function cambiarAprobador(int $tenantId, int $planId, int $aprobadorId, int $usuarioId): void;

    /** Reemplaza los participantes de la acción (altas y bajas explícitas). */
    public function reemplazarParticipantes(int $accionId, array $colaboradorIds, int $usuarioId): void;

    public function cancelarAccion(int $accionId, string $motivo, int $usuarioId): void;

    public function registrarHistorial(int $planId, ?int $accionId, TipoHistorialPlan $tipo, ?string $anterior, ?string $nuevo, ?string $motivo, int $usuarioId): void;

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function historial(int $tenantId, int $planId, int $pagina, int $porPagina): array;

    /**
     * Inscripciones en acciones no canceladas con fechas, de cualquier plan del tenant, para detectar cruces.
     *
     * @param  list<int>  $colaboradorIds
     * @return list<array{accion_id: int, plan_id: int, colaborador_id: int, inicio: string, fin: string}>
     */
    public function inscripcionesParaCruces(int $tenantId, array $colaboradorIds): array;

    /**
     * Acciones asignadas a un colaborador en planes Aprobado, Ejecutando o Cerrado.
     *
     * @return list<array<string, mixed>>
     */
    public function accionesDeColaborador(int $tenantId, int $colaboradorId): array;

    /**
     * Acciones no canceladas de planes Ejecutando con sus participantes (para Actividades Formativas).
     *
     * @return list<array<string, mixed>>
     */
    public function accionesDePlanesEjecutando(int $tenantId): array;

    /**
     * Planes con sus acciones para Reportes.
     *
     * @param  array{anio?: int, estado?: string, plan_id?: int}  $filtros
     * @return list<array<string, mixed>>
     */
    public function planesConAcciones(int $tenantId, array $filtros): array;
}
