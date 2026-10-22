<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Conocimientos con grado en las rutas de carrera
    |--------------------------------------------------------------------------
    |
    | Estructura Empresarial no tiene conocimientos con grado A–D; solo competencias con grado y requisitos de
    | texto del puesto (Formación, Capacitación y Experiencia). Con `false` (valor por defecto) la tarjeta
    | «Conocimientos» del detalle de nivel es solo informativa: sin grado, sin comparación y sin diferencial.
    |
    | Esta bandera es únicamente el punto de extensión documentado para el día en que Estructura incorpore
    | conocimientos con grado. La rama `true` NO está implementada (specs/019-rutas-carrera, research.md R3).
    |
    */

    'conocimientos_con_grado' => (bool) env('DESARROLLO_CONOCIMIENTOS_CON_GRADO', false),

];
