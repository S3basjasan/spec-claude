<?php

namespace App\Modules\Compensaciones\Application\UseCases;

use App\Modules\Compensaciones\Application\DTOs\CategoriaDTO;
use App\Modules\Compensaciones\Application\Ports\CompetenciasPort;
use App\Modules\Compensaciones\Application\Services\AlcanceDeLectura;
use App\Modules\Compensaciones\Application\Services\ArmadorDeCategoriaDTO;
use App\Modules\Compensaciones\Domain\Repositories\CategoriaRepository;
use App\Modules\Compensaciones\Domain\ValueObjects\EstadoCategoria;
use App\Modules\Compensaciones\Domain\ValueObjects\FiltroCategorias;
use App\Modules\Compensaciones\Domain\ValueObjects\Normalizador;

final class ListarCategorias
{
    public function __construct(
        private readonly CategoriaRepository $categorias,
        private readonly CompetenciasPort $competencias,
        private readonly ArmadorDeCategoriaDTO $armador,
    ) {}

    /**
     * El alcance lo decide el servidor: el Gestor ve todas (Activo por defecto, o Inactivo / Todos); el líder, todas las
     * activas; el colaborador, las activas asignadas a su unidad actual; quien no tiene ficha o unidad, ninguna.
     * Quien no es Gestor recibe siempre solo categorías Activo: el filtro de estado se ignora.
     *
     * @return array{items: array<int, CategoriaDTO>, total: int, pagina: int, porPagina: int, ultimaPagina: int}
     */
    public function ejecutar(
        ?string $busqueda,
        ?string $estadoPedido,
        ?int $competenciaId,
        int $pagina,
        AlcanceDeLectura $alcance,
    ): array {
        if ($alcance->esNinguno()) {
            return ['items' => [], 'total' => 0, 'pagina' => 1, 'porPagina' => FiltroCategorias::POR_PAGINA, 'ultimaPagina' => 1];
        }

        $texto = $busqueda === null ? null : Normalizador::normalizar($busqueda);
        $texto = $texto === '' ? null : $texto;

        $estado = EstadoCategoria::Activo;

        if ($alcance->esGestor() && $estadoPedido !== null) {
            $estado = $estadoPedido === 'Todos' ? null : EstadoCategoria::from($estadoPedido);
        }

        $idsPorNombre = $texto === null ? [] : $this->competencias->idsPorNombre($texto);

        $resultado = $this->categorias->buscar(new FiltroCategorias(
            $texto,
            $estado,
            $competenciaId,
            $pagina,
            $idsPorNombre,
            $alcance->unidadId,
            ! $alcance->esGestor(),
        ));

        return [
            'items' => $this->armador->varios($resultado->items, $alcance->veUnidades()),
            'total' => $resultado->total,
            'pagina' => $resultado->pagina,
            'porPagina' => $resultado->porPagina,
            'ultimaPagina' => $resultado->ultimaPagina,
        ];
    }
}
