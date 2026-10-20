<?php

namespace App\Modules\Compensaciones\Application\DTOs;

use App\Modules\Compensaciones\Domain\Entities\Categoria;
use App\Modules\Compensaciones\Domain\Exceptions\DatosDeCategoriaInvalidos;
use App\Modules\Compensaciones\Domain\ValueObjects\EstadoCategoria;

/**
 * Datos de entrada para crear o editar una categoría (ya validados en el borde HTTP).
 */
final class DatosCategoriaDTO
{
    /**
     * @param  array<int, string>  $indicadores
     */
    public function __construct(
        public readonly string $nombre,
        public readonly string $descripcion,
        public readonly int $competenciaId,
        public readonly mixed $puntuacionBase,
        public readonly string $icono,
        public readonly array $indicadores,
    ) {}

    /**
     * @throws DatosDeCategoriaInvalidos
     */
    public function comoPropuesta(EstadoCategoria $estado = EstadoCategoria::Activo): Categoria
    {
        return Categoria::propuesta(
            $this->nombre,
            $this->descripcion,
            $this->competenciaId,
            $this->puntuacionBase,
            $this->icono,
            $this->indicadores,
            $estado,
        );
    }
}
