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

        // GP-02: opciones fijas del producto; el vínculo guarda la clave, la etiqueta es de presentación.
        'tipos_contrato' => [
            'indefinido' => 'Indefinido',
            'plazo_fijo' => 'Plazo fijo',
            'temporal' => 'Temporal',
            'servicios_profesionales' => 'Servicios profesionales',
        ],
    ],

];
