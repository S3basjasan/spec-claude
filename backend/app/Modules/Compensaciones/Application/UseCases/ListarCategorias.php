<?php

namespace App\Modules\Compensaciones\Application\UseCases;

use App\Modules\Compensaciones\Application\DTOs\CategoriaDTO;
use App\Modules\Compensaciones\Application\Ports\CompetenciasPort;
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
     * Quien no puede gestionar recibe siempre solo categorías Activo: el filtro de estado se ignora.
     * El administrador recibe Activo por defecto, o Inactivo / Todos (`Todos` = sin filtro).
     *
     * @return array{items: array<int, CategoriaDTO>, total: int, pagina: int, porPagina: int, ultimaPagina: int}
     */
    public function ejecutar(
        ?string $busqueda,
        ?string $estadoPedido,
        ?int $competenciaId,
        int $pagina,
        bool $puedeGestionar,
    ): array {
        $texto = $busqueda === null ? null : Normalizador::normalizar($busqueda);
        $texto = $texto === '' ? null : $texto;

        $estado = EstadoCategoria::Activo;

        if ($puedeGestionar && $estadoPedido !== null) {
            $estado = $estadoPedido === 'Todos' ? null : EstadoCategoria::from($estadoPedido);
        }

        $idsPorNombre = $texto === null ? [] : $this->competencias->idsPorNombre($texto);

        $resultado = $this->categorias->buscar(new FiltroCategorias($texto, $estado, $competenciaId, $pagina, $idsPorNombre));

        return [
            'items' => $this->armador->varios($resultado->items),
            'total' => $resultado->total,
            'pagina' => $resultado->pagina,
            'porPagina' => $resultado->porPagina,
            'ultimaPagina' => $resultado->ultimaPagina,
        ];
    }
}
