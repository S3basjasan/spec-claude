<?php

namespace App\Modules\Estructura\Application\DTO;

/**
 * Datos del organigrama: las posiciones vigentes y las unidades activas (para el filtro). El árbol lo arma el cliente.
 */
final class OrganigramaDTO
{
    /**
     * @param  array<int, NodoDeOrganigramaDTO>  $posiciones  Por código.
     * @param  array<int, array{id: int, codigo: string, nombre: string, nivel_arbol: int, unidad_padre_id: int|null}>  $unidades  En orden de árbol.
     */
    public function __construct(
        public readonly array $posiciones,
        public readonly array $unidades,
    ) {}
}
