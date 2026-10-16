<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\DTO\DocumentoConCategoriaDTO;
use App\Modules\GestionPersonal\Application\Services\ResolvedorDeCategoriasDeDocumentos;
use App\Modules\GestionPersonal\Domain\Exceptions\ColaboradorNoEncontrado;
use App\Modules\GestionPersonal\Domain\Exceptions\DocumentoModificado;
use App\Modules\GestionPersonal\Domain\Exceptions\DocumentoNoEncontrado;
use App\Modules\GestionPersonal\Domain\Exceptions\EscrituraDeshabilitada;
use App\Modules\GestionPersonal\Domain\Repositories\ColaboradorRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Repositories\DocumentoRepositoryInterface;

final class CambiarVisibilidadDocumento
{
    public function __construct(
        private readonly ColaboradorRepositoryInterface $colaboradores,
        private readonly DocumentoRepositoryInterface $documentos,
        private readonly ResolvedorDeCategoriasDeDocumentos $categorias,
    ) {}

    /**
     * Solo sobre documentos Disponibles; el mismo valor con la versión vigente no escribe nada.
     *
     * @throws EscrituraDeshabilitada
     * @throws ColaboradorNoEncontrado
     * @throws DocumentoNoEncontrado
     * @throws DocumentoModificado
     */
    public function ejecutar(ContextoDeTenant $contexto, int $actorId, int $colaboradorId, int $documentoId, int $version, bool $visible): DocumentoConCategoriaDTO
    {
        if (! config('hrms.gestion_personal.documentos.habilitado', true)) {
            throw new EscrituraDeshabilitada;
        }

        $this->colaboradores->porId($contexto->tenantId, $colaboradorId) ?? throw new ColaboradorNoEncontrado;
        $actual = $this->documentos->porId($contexto->tenantId, $colaboradorId, $documentoId) ?? throw new DocumentoNoEncontrado;

        if ($actual->version !== $version || ! $actual->estaDisponible()) {
            throw new DocumentoModificado;
        }

        $documento = $actual->visibleColaborador === $visible
            ? $actual
            : $this->documentos->cambiarVisibilidad($contexto->tenantId, $colaboradorId, $documentoId, $version, $actorId, $visible);

        $categoria = $this->categorias->porIds($contexto->tenantId, [$documento->categoriaId])[$documento->categoriaId] ?? null;

        return new DocumentoConCategoriaDTO($documento, $categoria);
    }
}
