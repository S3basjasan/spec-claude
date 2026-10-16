<?php

/*
 * Mismos valores por defecto que el framework, con `Content-Disposition` expuesto: sin él el navegador no deja leer
 * desde JavaScript el nombre seguro del PDF (`Ficha_EMP-###.pdf`) cuando la API está en otro origen.
 */
return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Content-Disposition'],

    'max_age' => 0,

    'supports_credentials' => false,

];
