<?php

namespace App\Modules\Desarrollo\Domain\Repositories;

use App\Modules\Desarrollo\Domain\Entities\AccionDePdi;
use App\Modules\Desarrollo\Domain\Entities\HitoDePdi;
use App\Modules\Desarrollo\Domain\Entities\Pdi;
use App\Modules\Desarrollo\Domain\Exceptions\VersionDePdiDesfasada;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDeAccionPdi;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDeHito;
use App\Modules\Desarrollo\Domain\ValueObjects\ResultadoDeCompromiso;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDeHistorialPdi;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDePdi;
use DateTimeImmutable;

/**
 * Persistencia de los planes de desarrollo individual. No existe operación de borrado de planes: se cierran. El único
 * borrado físico es el reemplazo de compromisos, acciones e hitos de un plan en Borrador (research R10).
 */
interface RepositorioDePdiInterface
{
    public function transaccion(callable $accion): mixed;

    /** Crea el plan con el siguiente código `PDI-0001` del tenant, sus compromisos, acciones e hitos. */
    public function crear(Pdi $pdi, int $actorId): Pdi;

    public function obtener(int $tenantId, int $id): ?Pdi;

    /**
     * Todos los planes del colaborador, del más reciente al más antiguo, con su estructura.
     *
     * @return list<Pdi>
     */
    public function deColaborador(int $tenantId, int $colaboradorId): array;

    /**
     * Planes del tenant que cumplen los filtros (tipo, estado, colaborador_id, responsable_id, fecha_desde y fecha_hasta
     * sobre la fecha de fin), del más reciente al más antiguo, con su estructura.
     *
     * @param  array<string, mixed>  $filtros
     * @return list<Pdi>
     */
    public function listar(int $tenantId, array $filtros): array;

    /**
     * Conteo de planes por estado y de hitos Pendientes vencidos de planes no Cerrados.
     *
     * @return array{borrador: int, acordado: int, cerrado: int, hitos_vencidos: int}
     */
    public function indicadores(int $tenantId, string $hoy): array;

    /**
     * Planes no Cerrados de los colaboradores indicados: [{colaborador_id, tipo, puesto_objetivo_id}].
     *
     * @param  list<int>  $colaboradorIds
     * @return list<array{colaborador_id: int, tipo: string, puesto_objetivo_id: int|null}>
     */
    public function abiertosDeColaboradores(int $tenantId, array $colaboradorIds): array;

    /**
     * Actualiza la cabecera si la versión guardada coincide con `$versionEsperada` (control optimista).
     *
     * @throws VersionDePdiDesfasada
     */
    public function actualizarCabecera(Pdi $pdi, int $versionEsperada, int $actorId): void;

    /** Reemplaza compromisos, acciones e hitos del plan (solo Borrador) y devuelve el plan recargado. */
    public function reemplazarEstructura(Pdi $pdi, int $actorId): Pdi;

    /** Plan no Cerrado del colaborador para el tipo, o nulo. @return array{id: int, codigo: string}|null */
    public function planAbierto(int $tenantId, int $colaboradorId, TipoDePdi $tipo): ?array;

    /** @return array{pdi_id: int, accion: AccionDePdi}|null */
    public function obtenerAccion(int $tenantId, int $accionId): ?array;

    public function actualizarAccion(int $accionId, EstadoDeAccionPdi $estado, bool $congelado, ?int $planAccionId, int $actorId): void;

    /** @return array{pdi_id: int, hito: HitoDePdi}|null */
    public function obtenerHito(int $tenantId, int $hitoId): ?array;

    public function actualizarHito(int $hitoId, EstadoDeHito $estado, ?string $observaciones, int $actorId, DateTimeImmutable $ahora): void;

    /** @param  array<int, ResultadoDeCompromiso>  $resultados  compromiso_id => resultado */
    public function guardarResultadosDeCompromisos(array $resultados): void;

    public function registrarHistorial(
        int $tenantId,
        int $pdiId,
        TipoDeHistorialPdi $tipo,
        ?int $referenciaId,
        ?string $anterior,
        ?string $nuevo,
        ?string $motivo,
        int $usuarioId,
    ): void;

    /**
     * @return array{items: list<array{id: int, fecha: string, usuario_id: int, tipo: string, referencia_id: int|null, valor_anterior: string|null, valor_nuevo: string|null, motivo: string|null}>, total: int}
     */
    public function historial(int $tenantId, int $pdiId, int $pagina, int $porPagina): array;
}
