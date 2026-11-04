<?php

if (! function_exists('putParametro')) {
    function putParametro(string $clave, mixed $valor, int $version = 0)
    {
        return test()->putJson('/api/compensaciones/parametros', ['cambios' => [['clave' => $clave, 'valor' => $valor, 'version' => $version]]]);
    }
}

if (! function_exists('porClave')) {
    /** @return array<string, mixed> */
    function porClave(array $respuesta, string $clave): array
    {
        return collect($respuesta['data'])->firstWhere('clave', $clave);
    }
}
