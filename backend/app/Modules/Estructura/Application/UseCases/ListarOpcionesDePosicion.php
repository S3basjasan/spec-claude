<?php

namespace App\Modules\Estructura\Application\UseCases;

use App\Modules\Estructura\Application\DTO\OpcionesDePosicionDTO;
use App\Modules\Estructura\Application\Ports\NombresDeUsuariosInterface;
use App\Modules\Estructura\Domain\Repositories\PosicionRepositoryInterface;

final class ListarOpcionesDePosicion
{
    public function __construct(
        private readonly PosicionRepositoryInterface $posiciones,
        private readonly NombresDeUsuariosInterface $usuarios,
    ) {}

    /**
     * Puestos activos, unidades en orden de árbol con su disponibilidad y posiciones candidatas a supervisor. Con
     * `excluir` (la posición que se edita) las candidatas no incluyen esa posición ni sus subordinadas.
     */
    public function ejecutar(?int $excluir = null): OpcionesDePosicionDTO
    {
        $opciones = $this->posiciones->opciones($excluir);

        $ocupantes = array_values(array_unique(array_filter(
            array_column($opciones->posiciones, 'ocupante_id'),
            fn ($id) => $id !== null,
        )));

        return OpcionesDePosicionDTO::desde($opciones, $ocupantes === [] ? [] : $this->usuarios->nombres($ocupantes));
    }
}
