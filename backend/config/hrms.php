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
    ],

];
