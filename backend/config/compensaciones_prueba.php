<?php

/*
|--------------------------------------------------------------------------
| Configuración SOLO del repositorio de prueba
|--------------------------------------------------------------------------
|
| Este archivo no se usa en el proyecto real. Sustituye, de forma provisional, datos que Gestión de Personal
| todavía no publica (líder y equipo de cada colaborador) y el tenant cuando no hay sesión. Los ids deben coincidir
| con los de `PersonalSeeder`. Cuando esos métodos existan se reemplaza `EquipoDesdeConfiguracion` por
| `EquipoDesdePersonal` y este archivo se elimina.
|
*/

return [

    /** Tenant que se usa cuando no hay sesión (comandos y tareas programadas). */
    'tenant_id' => 1,

    /** Líder (colaborador_id) => colaboradores de su equipo (colaborador_id). */
    'equipos' => [
        // 1 => [2, 3],
    ],

    /** Colaborador (colaborador_id) => usuario (user_id). */
    'usuarios' => [
        // 2 => 5,
    ],

];
