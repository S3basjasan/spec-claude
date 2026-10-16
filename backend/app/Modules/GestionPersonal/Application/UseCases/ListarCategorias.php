<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\DTO\FiltroCategoriasDTO;
use App\Modules\GestionPersonal\Application\DTO\ListadoCategoriasDTO;
use App\Modules\GestionPersonal\Domain\Repositories\CategoriaRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Services\NormalizadorDeTexto;

final class ListarCategorias
{
    public function __construct(
        private readonly CategoriaRepositoryInterface $categorias,
        private readonly NormalizadorDeTexto $normalizador,
    ) {}

    public function ejecutar(ContextoDeTenant $contexto, FiltroCategoriasDTO $filtro): ListadoCategoriasDTO
    {
        $q = $filtro->q === null ? null : $this->normalizador->busquedaDeTexto($filtro->q);

        $resultado = $this->categorias->listar($contexto->tenantId, $q, $filtro->estado, $filtro->pagina, $filtro->porPagina);

        return new ListadoCategoriasDTO($resultado['items'], $resultado['total'], $filtro->pagina, $filtro->porPagina);
    }
}
