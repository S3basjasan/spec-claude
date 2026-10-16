<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContenidoDeDocumentoDTO;
use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\Ports\AlmacenDeDocumentosPort;
use App\Modules\GestionPersonal\Domain\Entities\DocumentoColaborador;
use App\Modules\GestionPersonal\Domain\Exceptions\AlmacenamientoNoDisponible;
use App\Modules\GestionPersonal\Domain\Exceptions\ColaboradorNoEncontrado;
use App\Modules\GestionPersonal\Domain\Exceptions\DocumentoNoEncontrado;
use App\Modules\GestionPersonal\Domain\Repositories\ColaboradorRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Repositories\DocumentoRepositoryInterface;

final class ObtenerContenidoDocumento
{
    public function __construct(
        private readonly ColaboradorRepositoryInterface $colaboradores,
        private readonly DocumentoRepositoryInterface $documentos,
        private readonly AlmacenDeDocumentosPort $almacen,
    ) {}

    /**
     * El Administrador accede a documentos Disponibles y Archivados de su tenant.
     *
     * @throws ColaboradorNoEncontrado
     * @throws DocumentoNoEncontrado
     * @throws AlmacenamientoNoDisponible
     */
    public function ejecutar(ContextoDeTenant $contexto, int $colaboradorId, int $documentoId): ContenidoDeDocumentoDTO
    {
        $this->colaboradores->porId($contexto->tenantId, $colaboradorId) ?? throw new ColaboradorNoEncontrado;
        $documento = $this->documentos->porId($contexto->tenantId, $colaboradorId, $documentoId) ?? throw new DocumentoNoEncontrado;

        return $this->abrir($documento);
    }

    public function abrir(DocumentoColaborador $documento): ContenidoDeDocumentoDTO
    {
        // El flujo se abre antes de enviar un byte: un fallo del almacén nunca produce un cuerpo parcial.
        $flujo = $this->almacen->leerComoFlujo($documento->objectKey);

        return new ContenidoDeDocumentoDTO($flujo, $documento->sizeBytes, "Documento_{$documento->id}.pdf");
    }
}
