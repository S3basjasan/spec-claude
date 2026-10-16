<?php

namespace App\Modules\GestionPersonal\Domain\Repositories;

use App\Modules\GestionPersonal\Domain\Entities\DocumentoColaborador;
use App\Modules\GestionPersonal\Domain\Exceptions\ConflictoDeUnicidad;
use App\Modules\GestionPersonal\Domain\Exceptions\DocumentoModificado;
use App\Modules\GestionPersonal\Domain\Exceptions\DocumentoNoEncontrado;

/**
 * Persistencia de los documentos del colaborador. Sin ningún método de eliminación.
 */
interface DocumentoRepositoryInterface
{
    public function porId(int $tenantId, int $colaboradorId, int $id): ?DocumentoColaborador;

    /**
     * @param  'Disponible'|'Archivado'  $estado
     * @return array{items: list<DocumentoColaborador>, total: int} Del más reciente al más antiguo (fecha de carga, id).
     */
    public function listar(int $tenantId, int $colaboradorId, ?string $q, ?int $categoriaId, string $estado, bool $soloVisibles, int $pagina, int $porPagina): array;

    /** @return array{documento: DocumentoColaborador, hash: string, actor: int}|null */
    public function porOperacion(int $tenantId, string $operacionId): ?array;

    /**
     * Inserta el documento ya almacenado como Disponible.
     *
     * @throws ConflictoDeUnicidad Operación o clave de objeto repetidas.
     */
    public function publicar(
        int $tenantId,
        int $colaboradorId,
        int $categoriaId,
        int $actorId,
        string $titulo,
        string $tituloBusqueda,
        string $objectKey,
        string $mimeType,
        int $sizeBytes,
        string $sha256,
        bool $visible,
        string $operacionId,
        string $operacionHash,
    ): DocumentoColaborador;

    /**
     * Solo sobre documentos Disponibles.
     *
     * @throws DocumentoNoEncontrado
     * @throws DocumentoModificado Versión obsoleta o documento archivado.
     */
    public function cambiarVisibilidad(int $tenantId, int $colaboradorId, int $id, int $version, int $actorId, bool $visible): DocumentoColaborador;

    /**
     * @throws DocumentoNoEncontrado
     * @throws DocumentoModificado Versión obsoleta o ya archivado.
     */
    public function archivar(int $tenantId, int $colaboradorId, int $id, int $version, int $actorId): DocumentoColaborador;

    /** Si alguna fila usa esa clave de objeto. */
    public function claveReferenciada(string $objectKey): bool;
}
