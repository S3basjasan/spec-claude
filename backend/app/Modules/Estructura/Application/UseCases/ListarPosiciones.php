<?php

namespace App\Modules\Estructura\Application\UseCases;

use App\Modules\Estructura\Application\DTO\ListadoPosicionesDTO;
use App\Modules\Estructura\Application\DTO\PosicionDTO;
use App\Modules\Estructura\Application\Ports\NombresDeUsuariosInterface;
use App\Modules\Estructura\Domain\Repositories\PosicionRepositoryInterface;
use App\Modules\Estructura\Domain\ValueObjects\FiltroDePosiciones;

final class ListarPosiciones
{
    public function __construct(
        private readonly PosicionRepositoryInterface $posiciones,
        private readonly NombresDeUsuariosInterface $usuarios,
    ) {}

    /**
     * El filtro ya llega con el estado resuelto según el rol (la petición decide qué puede pedir quien no es
     * administrador). La búsqueda también encuentra por nombre del ocupante: los ids de usuario salen del puerto, sin
     * leer la tabla de usuarios. Orden por código ascendente, paginado.
     */
    public function ejecutar(FiltroDePosiciones $filtro): ListadoPosicionesDTO
    {
        $busqueda = $filtro->q === null ? '' : trim($filtro->q);
        if ($busqueda !== '') {
            $filtro = new FiltroDePosiciones(
                $filtro->q,
                $filtro->estado,
                $filtro->unidadId,
                $filtro->pagina,
                $filtro->porPagina,
                $this->usuarios->idsPorNombre($busqueda),
            );
        }

        $pagina = $this->posiciones->listar($filtro);

        $ocupantes = array_values(array_unique(array_filter(
            array_map(fn ($posicion) => $posicion->ocupanteId, $pagina->items),
            fn ($id) => $id !== null,
        )));
        $nombres = $ocupantes === [] ? [] : $this->usuarios->nombres($ocupantes);

        return new ListadoPosicionesDTO(
            array_map(fn ($posicion) => PosicionDTO::desde($posicion, $nombres), $pagina->items),
            $pagina->total,
            $pagina->pagina,
            $pagina->porPagina,
        );
    }
}
