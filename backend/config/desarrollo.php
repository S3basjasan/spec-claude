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

    /*
    |--------------------------------------------------------------------------
    | Adecuación a puesto futuro (feature 020)
    |--------------------------------------------------------------------------
    |
    | `conocimientos_con_grado`: con `false` (valor por defecto) la dimensión Conocimientos queda desactivada: no se
    | generan resultados, el diagnóstico muestra un aviso informativo y los pesos de las demás dimensiones se reescalan.
    | La rama `true` no está implementada (specs/020-adecuacion-puesto-futuro, research.md R4).
    |
    | `experiencia_disponible`: con `false` la experiencia acumulada no se lee de Gestión de Personal y la dimensión
    | Experiencia queda sin evaluar.
    |
    */

    'adecuacion' => [
        'conocimientos_con_grado' => (bool) env('DESARROLLO_ADECUACION_CONOCIMIENTOS_CON_GRADO', false),
        'experiencia_disponible' => (bool) env('DESARROLLO_ADECUACION_EXPERIENCIA_DISPONIBLE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Plan de desarrollo individual (feature 021)
    |--------------------------------------------------------------------------
    |
    | `conocimientos_con_grado`: con `false` (valor por defecto) la dimensión Conocimiento se desactiva en los
    | compromisos: la precarga no genera conocimientos, `GET /api/pdi/opciones` no la lista en `dimensiones_disponibles`
    | y crear o editar un compromiso de Conocimiento responde 422. La rama `true` no está implementada
    | (specs/021-plan-desarrollo-individual, research.md R5).
    |
    */

    'pdi' => [
        'conocimientos_con_grado' => (bool) env('DESARROLLO_PDI_CONOCIMIENTOS_CON_GRADO', false),
    ],

];
