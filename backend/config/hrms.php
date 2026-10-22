<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Contraseña de los usuarios de prueba
    |--------------------------------------------------------------------------
    |
    | Solo para desarrollo y pruebas (feature 000-login). Los usuarios iniciales
    | se siembran con esta contraseña; no usar datos reales.
    |
    */

    'seed_password' => env('SEED_PASSWORD', 'password-de-prueba'),

    /*
    |--------------------------------------------------------------------------
    | Gestión de Personal (feature 005)
    |--------------------------------------------------------------------------
    |
    | `escritura_habilitada` en false detiene el alta y la edición de fichas (503)
    | conservando las existentes y las consultas: es el mecanismo de rollback.
    |
    */

    'gestion_personal' => [
        'escritura_habilitada' => (bool) env('GESTION_PERSONAL_ESCRITURA_HABILITADA', true),

        // GP-02: en false se detiene el registro de ingresos (503) conservando vínculos y consultas.
        'ingresos_habilitados' => (bool) env('GESTION_PERSONAL_INGRESOS_HABILITADOS', true),

        // Exportación individual de ficha (feature 015): en false las rutas responden 503 sin generar.
        'exportacion_habilitada' => (bool) env('GESTION_PERSONAL_EXPORTACION_HABILITADA', true),

        // Documentos del colaborador (feature 014): archivos PDF privados.
        // Políticas operativas de personal (feature 016): en false se detienen las escrituras (503) conservando versiones y consultas.
        'politicas_habilitado' => (bool) env('GESTION_PERSONAL_POLITICAS_HABILITADO', true),

        'documentos' => [
            // En false se detienen cargas y cambios (503) conservando documentos y consultas.
            'habilitado' => (bool) env('GESTION_PERSONAL_DOCUMENTOS_HABILITADOS', true),
            // Disco privado de Laravel (nunca `public`); `local` = storage/app/private.
            'disco' => env('DOCUMENTOS_DISCO', 'local'),
            'max_bytes' => 10000000,
            // Antigüedad mínima de un objeto sin referencia para que el barrido lo elimine.
            'huerfanos_horas' => 24,
        ],

        // GP-02: opciones fijas del producto; el vínculo guarda la clave, la etiqueta es de presentación.
        'tipos_contrato' => [
            'indefinido' => 'Indefinido',
            'plazo_fijo' => 'Plazo fijo',
            'temporal' => 'Temporal',
            'servicios_profesionales' => 'Servicios profesionales',
        ],
    ],

];
