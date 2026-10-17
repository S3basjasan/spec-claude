<?php

namespace App\Modules\Desarrollo\Domain\Repositories;

use App\Modules\Desarrollo\Domain\Entities\Actividad;
use App\Modules\Desarrollo\Domain\Exceptions\VersionDesactualizada;
use App\Modules\Desarrollo\Domain\ValueObjects\CambioDeAsistencia;
use App\Modules\Desarrollo\Domain\ValueObjects\RegistroDeAsistencia;

/**
 * Persistencia de las actividades formativas y su asistencia. Todas las consultas se acotan al tenant cuando
 * lo reciben. Nunca elimina actividades ni historial; solo elimina la fila de un registro al volver a Pendiente.
 */
interface ActividadRepositoryInterface
{
    /** Crea la actividad con el siguiente número del tenant (ACT-NNNN). */
    public function crear(Actividad $actividad): Actividad;

    public function actualizar(int $actividadId, string $lugarEnlace, ?int $departamentoId, int $usuarioId): void;

    public function obtener(int $tenantId, int $id): ?Actividad;

    public function obtenerPorAccion(int $tenantId, int $accionId): ?Actividad;

    /** @return list<Actividad> */
    public function listarDelTenant(int $tenantId): array;

    /**
     * @param  list<int>  $accionIds
     * @return list<Actividad>
     */
    public function porAcciones(int $tenantId, array $accionIds): array;

    /** @return list<int> ids de acción que ya tienen actividad */
    public function idsDeAccionesConActividad(int $tenantId): array;

    /** @return array<int, RegistroDeAsistencia> colaborador_id => registro */
    public function registrosDe(int $actividadId): array;

    /**
     * Colaboradores con registro, por actividad.
     *
     * @param  list<int>  $actividadIds
     * @return array<int, list<int>> actividad_id => colaborador_ids
     */
    public function registradosPorActividades(array $actividadIds): array;

    /**
     * Guarda en una transacción los registros nuevos o modificados, elimina los que vuelven a Pendiente, escribe el
     * historial y sube la versión. Devuelve la nueva versión.
     *
     * @param  list<RegistroDeAsistencia>  $guardar
     * @param  list<int>  $eliminar  colaboradores que vuelven a Pendiente
     * @param  list<CambioDeAsistencia>  $historial
     *
     * @throws VersionDesactualizada
     */
    public function guardarAsistencia(int $actividadId, int $versionEsperada, array $guardar, array $eliminar, array $historial, int $usuarioId): int;

    /** @return array{items: list<array<string, mixed>>, total: int} del más reciente al más antiguo */
    public function historial(int $actividadId, int $pagina, int $porPagina): array;

    /**
     * Calificados y suma de calificaciones por actividad (solo las que tienen al menos una).
     *
     * @param  list<int>  $actividadIds
     * @return array<int, array{calificados: int, suma: int}>
     */
    public function agregadosPorActividad(array $actividadIds): array;

    /**
     * Actividades calificadas y suma de calificaciones por colaborador (solo quienes tienen al menos una).
     *
     * @param  list<int>  $actividadIds
     * @return array<int, array{calificadas: int, suma: int}> colaborador_id => agregado
     */
    public function agregadosPorColaborador(array $actividadIds): array;

    /**
     * Registros de un colaborador en las actividades dadas.
     *
     * @param  list<int>  $actividadIds
     * @return array<int, RegistroDeAsistencia> actividad_id => registro
     */
    public function registrosDeColaborador(array $actividadIds, int $colaboradorId): array;

    /**
     * Registros (sin observaciones) de las actividades dadas, para Reportes.
     *
     * @param  list<int>  $actividadIds
     * @return list<array{actividad_id: int, registro: RegistroDeAsistencia}>
     */
    public function registrosDeActividades(array $actividadIds): array;

    public function tieneAsistencia(int $actividadId, int $colaboradorId): bool;

    /**
     * Ids de acción, entre los dados, cuya actividad tiene asistencia Presente del colaborador.
     *
     * @param  list<int>  $accionIds
     * @return list<int>
     */
    public function accionesConPresente(array $accionIds, int $colaboradorId): array;
}
