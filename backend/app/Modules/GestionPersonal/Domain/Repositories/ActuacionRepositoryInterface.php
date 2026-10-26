<?php

namespace App\Modules\GestionPersonal\Domain\Repositories;

use App\Modules\GestionPersonal\Domain\Entities\Actuacion;
use App\Modules\GestionPersonal\Domain\Exceptions\ConflictoDeUnicidad;
use App\Modules\GestionPersonal\Domain\Exceptions\MovimientoIncompatible;
use App\Modules\GestionPersonal\Domain\Exceptions\SolicitudModificada;
use App\Modules\GestionPersonal\Domain\ValueObjects\EstadoDeActuacion;

/**
 * Persistencia de actuaciones, detalles, respaldos referenciados y transiciones. Sin ningún método de eliminación;
 * toda lectura y escritura lleva el tenant. Los métodos de escritura se llaman dentro de una transacción abierta.
 */
interface ActuacionRepositoryInterface
{
    public function porId(int $tenantId, int $id): ?Actuacion;

    /** @return array{actuacion: Actuacion, hash: string, actor: int}|null Presentación ya registrada con esa clave. */
    public function porOperacion(int $tenantId, string $operacionId): ?array;

    /** @return array{actuacion_id: int, hash: string, actor_id: int|null, evento: string}|null Transición ya registrada con esa clave. */
    public function transicionPorOperacion(int $tenantId, string $operacionId): ?array;

    /**
     * Inserta la actuación con su detalle, sus respaldos y la transición `Presentada`/`Informada` en un solo paso.
     *
     * @param  array<string, mixed>  $comun  Columnas de `actuaciones_personal`.
     * @param  array<string, mixed>  $detalle  Columnas de `novedades_personal` o `movimientos_personal`.
     * @param  list<int>  $documentoIds
     *
     * @throws ConflictoDeUnicidad Clave de operación repetida.
     */
    public function insertar(array $comun, array $detalle, array $documentoIds): Actuacion;

    /**
     * Cambia el estado con control optimista (versión y estado esperados), actualiza columnas de detalle y agrega la
     * transición. Un estado igual al actual solo se admite en el reintento de una incidencia.
     *
     * @param  array<string, mixed>  $novedad  Columnas de `novedades_personal` a actualizar.
     * @param  array<string, mixed>  $movimiento  Columnas de `movimientos_personal` a actualizar.
     * @param  array<string, mixed>  $detalleDeTransicion  Información segura que se serializa en la transición.
     *
     * @throws SolicitudModificada
     * @throws MovimientoIncompatible Ya hay otro movimiento aprobado pendiente de la misma persona.
     * @throws ConflictoDeUnicidad Clave de operación repetida.
     */
    public function cambiarEstado(
        Actuacion $actual,
        EstadoDeActuacion $nuevo,
        string $evento,
        ?int $actorId,
        ?string $justificacion,
        array $detalleDeTransicion = [],
        ?string $operacionId = null,
        ?string $operacionHash = null,
        array $novedad = [],
        array $movimiento = [],
    ): Actuacion;

    /**
     * Listado paginado del tenant, del más reciente al más antiguo.
     *
     * @param  array{tipo?: string|null, estado?: string|null, grupo?: string|null, colaborador_id?: int|null, desde?: string|null, hasta?: string|null}  $filtros
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function listar(int $tenantId, array $filtros, int $pagina, int $porPagina): array;

    /** @return array{pendientes: int, en_revision: int, programados: int, aplicados: int, incidencias: int} */
    public function resumen(int $tenantId): array;

    /**
     * Solicitudes de la persona, de la más reciente a la más antigua (hasta 200).
     *
     * @return list<Actuacion>
     */
    public function delColaborador(int $tenantId, int $colaboradorId): array;

    /**
     * Novedades de la persona con su periodo, para detectar cruces.
     *
     * @return list<array{id: int, tipo: string, estado: string, inicio: string, fin: string}>
     */
    public function periodosDeNovedades(int $tenantId, int $colaboradorId): array;

    /** @return list<array<string, mixed>> Transiciones en orden, con el nombre del actor. */
    public function historial(int $tenantId, int $actuacionId): array;

    /** @return list<int> Tenants con movimientos aprobados cuya aplicación sigue pendiente. */
    public function tenantsConMovimientosPorAplicar(): array;

    /**
     * Movimientos `Aprobada`/`Programada` con aplicación pendiente y fecha efectiva alcanzada.
     *
     * @return list<int> Identificadores de actuación, por fecha efectiva.
     */
    public function movimientosPorAplicar(int $tenantId, string $hastaFecha, int $limite): array;

    /**
     * Hechos laborales derivados de movimientos efectivamente aplicados (para el Historial Laboral), por fecha efectiva.
     *
     * @return list<array{actuacion_id: int, vinculo_id: int, tipo: string, fecha_efectiva: string, posicion_id: int, politica_numero: int, aplicada_en: string}>
     */
    public function hechosAplicados(int $tenantId, int $colaboradorId): array;

    /** @return array{nombre: string, codigo: string}|null Nombre y código de la ficha del tenant. */
    public function fichaResumen(int $tenantId, int $colaboradorId): ?array;

    /** @return array<int, string> Nombres de cuentas por identificador. */
    public function nombresDeCuentas(array $userIds): array;
}
