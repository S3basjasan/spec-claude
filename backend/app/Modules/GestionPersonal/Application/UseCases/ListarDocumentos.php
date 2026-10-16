<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\DTO\DocumentoConCategoriaDTO;
use App\Modules\GestionPersonal\Application\DTO\FiltroDocumentosDTO;
use App\Modules\GestionPersonal\Application\DTO\ListadoDocumentosDTO;
use App\Modules\GestionPersonal\Application\Services\ResolvedorDeCategoriasDeDocumentos;
use App\Modules\GestionPersonal\Domain\Exceptions\ColaboradorNoEncontrado;
use App\Modules\GestionPersonal\Domain\Repositories\ColaboradorRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Repositories\DocumentoRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Services\NormalizadorDeTexto;

final class ListarDocumentos
{
    public function __construct(
        private readonly ColaboradorRepositoryInterface $colaboradores,
        private readonly DocumentoRepositoryInterface $documentos,
        private readonly ResolvedorDeCategoriasDeDocumentos $categorias,
        private readonly NormalizadorDeTexto $normalizador,
    ) {}

    /** @throws ColaboradorNoEncontrado Inexistente o de otro tenant: la misma respuesta. */
    public function ejecutar(ContextoDeTenant $contexto, int $colaboradorId, FiltroDocumentosDTO $filtro): ListadoDocumentosDTO
    {
        $this->colaboradores->porId($contexto->tenantId, $colaboradorId) ?? throw new ColaboradorNoEncontrado;

        return $this->listar($contexto, $colaboradorId, $filtro, false);
    }

    public function listar(ContextoDeTenant $contexto, int $colaboradorId, FiltroDocumentosDTO $filtro, bool $soloVisibles): ListadoDocumentosDTO
    {
        $q = $filtro->q === null ? null : $this->normalizador->busquedaDeTexto($filtro->q);

        $resultado = $this->documentos->listar($contexto->tenantId, $colaboradorId, $q, $filtro->categoriaId, $filtro->estado, $soloVisibles, $filtro->pagina, $filtro->porPagina);

        $categorias = $this->categorias->porIds($contexto->tenantId, array_map(fn ($d) => $d->categoriaId, $resultado['items']));

        return new ListadoDocumentosDTO(
            array_map(fn ($d) => new DocumentoConCategoriaDTO($d, $categorias[$d->categoriaId] ?? null), $resultado['items']),
            $resultado['total'],
            $filtro->pagina,
            $filtro->porPagina,
        );
    }
}
