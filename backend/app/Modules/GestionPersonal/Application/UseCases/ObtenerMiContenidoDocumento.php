<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContenidoDeDocumentoDTO;
use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Domain\Exceptions\DocumentoNoEncontrado;
use App\Modules\GestionPersonal\Domain\Repositories\DocumentoRepositoryInterface;

final class ObtenerMiContenidoDocumento
{
    public function __construct(
        private readonly ResolverFichaDocumentalPropia $propia,
        private readonly DocumentoRepositoryInterface $documentos,
        private readonly ObtenerContenidoDocumento $contenido,
    ) {}

    /**
     * Revalida cuenta, ficha, permiso, visibilidad y estado en cada apertura y descarga. Privado, archivado, ajeno o
     * de otro tenant: la misma respuesta, sin bytes.
     *
     * @throws DocumentoNoEncontrado
     */
    public function ejecutar(ContextoDeTenant $contexto, int $userId, int $documentoId): ContenidoDeDocumentoDTO
    {
        $colaboradorId = $this->propia->ejecutar($contexto, $userId);

        $documento = $this->documentos->porId($contexto->tenantId, $colaboradorId, $documentoId);
        if ($documento === null || ! $documento->visibleColaborador || ! $documento->estaDisponible()) {
            throw new DocumentoNoEncontrado;
        }

        return $this->contenido->abrir($documento);
    }
}
