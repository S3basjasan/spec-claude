<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Catálogo de puestos
    |--------------------------------------------------------------------------
    |
    | Límite de elementos de cada lista de un puesto (funciones, requisitos y
    | competencias). Es configurable; por defecto 20 (specs/004-catalogo-puestos).
    |
    */

    'puestos' => [
        'max_elementos_por_lista' => (int) env('PUESTOS_MAX_ELEMENTOS_POR_LISTA', 20),
    ],

];
