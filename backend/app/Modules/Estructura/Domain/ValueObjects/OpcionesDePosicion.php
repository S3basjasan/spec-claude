<?php

namespace App\Modules\Estructura\Domain\ValueObjects;

/**
 * Datos de los selectores del formulario de posiciones.
 */
final class OpcionesDePosicion
{
    /**
     * @param  array<int, array{id: int, codigo: string, nombre: string, descripcion: string}>  $puestos  Puestos activos.
     * @param  array<int, array{id: int, codigo: string, nombre: string, tipo: array{id: int, nombre: string, nivel: int}, nivel_arbol: int, estado: string, unidad_padre_id: int|null, superiores: array<int, int>, habilitada: bool, motivo_deshabilitada: string|null}>  $unidades  En orden de árbol.
     * @param  array<int, array{id: int, codigo: string, puesto: string, ocupante_id: int|null, unidad_organizacional_id: int, unidad: string}>  $posiciones  Candidatas a supervisor (no Suprimidas).
     */
    public function __construct(
        public readonly array $puestos,
        public readonly array $unidades,
        public readonly array $posiciones,
        public readonly bool $hayMaximaAutoridad,
        /** @var array<int, int> Con `excluir`: ids de las unidades de las subordinadas directas vigentes de esa posición. */
        public readonly array $unidadesMinimas = [],
    ) {}
}
