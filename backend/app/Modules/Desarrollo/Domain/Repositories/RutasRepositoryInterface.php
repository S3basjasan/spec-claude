<?php

namespace App\Modules\Desarrollo\Domain\Repositories;

use App\Modules\Desarrollo\Domain\Entities\EventoDeRuta;
use App\Modules\Desarrollo\Domain\Entities\InscripcionARuta;
use App\Modules\Desarrollo\Domain\Entities\Ruta;
use App\Modules\Desarrollo\Domain\Exceptions\ConflictoDeVersionRuta;
use App\Modules\Desarrollo\Domain\Exceptions\InscripcionNoPermitida;
use App\Modules\Desarrollo\Domain\Exceptions\NivelEnUso;
use App\Modules\Desarrollo\Domain\Exceptions\RutaInvalida;
use App\Modules\Desarrollo\Domain\Exceptions\RutaNoEncontrada;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDeRuta;

/**
 * Persistencia de las rutas de carrera, sus niveles, sus inscripciones y su historial. Todas las consultas se
 * acotan al tenant. No existe ninguna operación para eliminar rutas, inscripciones ni historial: las rutas se
 * archivan y las inscripciones se finalizan o se retiran. Los niveles solo se eliminan dentro de `guardar`.
 */
interface RutasRepositoryInterface
{
    /**
     * Crea la ruta con el siguiente código CAR-NNNN del tenant, sus niveles y los eventos del historial.
     *
     * @param  list<EventoDeRuta>  $eventos
     *
     * @throws RutaInvalida con `nombre_duplicado` si el nombre ya existe
     */
    public function crear(Ruta $ruta, array $eventos): Ruta;

    public function obtener(int $tenantId, int $id): ?Ruta;

    /**
     * Reemplaza los datos y los niveles en una transacción conservando el id de los niveles existentes, sube la versión
     * y escribe el historial. Los niveles sin id se crean; los existentes que no vienen se eliminan.
     *
     * @param  list<EventoDeRuta>  $eventos
     *
     * @throws RutaNoEncontrada
     * @throws ConflictoDeVersionRuta
     * @throws NivelEnUso si se quita un nivel referenciado por alguna inscripción
     * @throws RutaInvalida con `nombre_duplicado`
     */
    public function guardar(Ruta $ruta, int $versionEsperada, array $eventos): Ruta;

    /**
     * @throws RutaNoEncontrada
     * @throws ConflictoDeVersionRuta
     */
    public function cambiarEstado(int $tenantId, int $rutaId, int $versionEsperada, EstadoDeRuta $estado, int $usuarioId, EventoDeRuta $evento): Ruta;

    public function existeNombre(int $tenantId, string $clave, ?int $exceptoId = null): bool;

    /**
     * @param  array{tipo?: string|null, estado?: string|null, departamento_id?: int|null, q?: string|null, solo_vigentes?: bool}  $filtro
     * @return array{items: list<array{id: int, codigo: string, nombre: string, descripcion: string|null, tipo: string, estado: string, departamento_id: int|null, niveles_count: int, inscritos_count: int}>, total: int}
     */
    public function listar(int $tenantId, array $filtro, int $pagina, int $porPagina): array;

    /** @return list<array{id: int, codigo: string, nombre: string, tipo: string, niveles_count: int}> */
    public function vigentes(int $tenantId): array;

    /** @return array<int, int> nivel_id => inscripciones Activas que lo tienen como nivel actual u objetivo */
    public function nivelesEnUso(int $rutaId): array;

    /** @return list<int> ids de nivel referenciados por inscripciones de cualquier estado */
    public function nivelesConInscripciones(int $rutaId): array;

    /** @return list<InscripcionARuta> */
    public function activasDeRuta(int $rutaId): array;

    /**
     * @throws InscripcionNoPermitida con `ya_inscrito` si el colaborador ya tiene una Activa en la ruta
     */
    public function crearInscripcion(InscripcionARuta $inscripcion, EventoDeRuta $evento): InscripcionARuta;

    public function obtenerInscripcion(int $tenantId, int $id): ?InscripcionARuta;

    public function guardarInscripcion(InscripcionARuta $inscripcion, EventoDeRuta $evento): InscripcionARuta;

    /**
     * @param  array{ruta_id?: int|null, colaborador_id?: int|null, estado?: string|null}  $filtro
     * @return array{items: list<InscripcionARuta>, total: int}
     */
    public function listarInscripciones(int $tenantId, array $filtro, int $pagina, int $porPagina): array;

    /** @return list<InscripcionARuta> Activas del colaborador */
    public function activasDeColaborador(int $tenantId, int $colaboradorId): array;

    /** @return list<int> colaboradores con alguna inscripción Activa */
    public function colaboradoresConActivas(int $tenantId): array;

    /**
     * @param  list<int>  $colaboradorIds
     * @return list<InscripcionARuta>
     */
    public function activasDeColaboradores(int $tenantId, array $colaboradorIds): array;

    /** @return array{nivel_id: int, ruta_id: int, ruta_codigo: string, ruta_nombre: string, ruta_tipo: string, orden: int, puesto_id: int, nombre: string, experiencia_minima_anios: float|null}|null */
    public function nivelPorId(int $tenantId, int $nivelId): ?array;

    /** @return array{items: list<array<string, mixed>>, total: int} del más reciente al más antiguo */
    public function historial(int $tenantId, int $rutaId, int $pagina, int $porPagina): array;
}
