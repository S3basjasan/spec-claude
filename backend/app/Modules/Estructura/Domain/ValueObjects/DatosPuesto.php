<?php

namespace App\Modules\Estructura\Domain\ValueObjects;

/**
 * Contenido editable de un puesto: es la entrada de los casos de uso y del repositorio, de modo que ni el
 * dominio ni la aplicación dependen de DTO ni de peticiones HTTP.
 */
final class DatosPuesto
{
    /**
     * @param  array<int, FuncionPuesto>  $funciones  En el orden definido por el usuario (`orden` desde 1).
     * @param  array<int, RequisitoPuesto>  $requisitos
     * @param  array<int, AsignacionDeCompetencia>  $competencias
     */
    public function __construct(
        public readonly string $nombre,
        public readonly string $descripcion,
        public readonly string $mision,
        public readonly array $funciones,
        public readonly array $requisitos,
        public readonly array $competencias,
    ) {}
}
