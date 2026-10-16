<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\DTO\FiltroDocumentosDTO;
use App\Modules\GestionPersonal\Application\DTO\ListadoDocumentosDTO;

final class ListarMisDocumentos
{
    public function __construct(
        private readonly ResolverFichaDocumentalPropia $propia,
        private readonly ListarDocumentos $listar,
    ) {}

    /** Solo documentos visibles y Disponibles de la ficha propia; cualquier estado recibido se ignora. */
    public function ejecutar(ContextoDeTenant $contexto, int $userId, FiltroDocumentosDTO $filtro): ListadoDocumentosDTO
    {
        $colaboradorId = $this->propia->ejecutar($contexto, $userId);

        $disponibles = new FiltroDocumentosDTO($filtro->q, $filtro->categoriaId, 'Disponible', $filtro->pagina, $filtro->porPagina);

        return $this->listar->listar($contexto, $colaboradorId, $disponibles, true);
    }
}
