<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Services\AutorizadorDePdi;

/** Claves de permiso `desarrollo.pdi.*` del usuario autenticado, para que la interfaz decida qué mostrar. */
final class ObtenerPermisosDePdi
{
    public function __construct(private readonly AutorizadorDePdi $autorizador) {}

    /** @return array{claves: list<string>} */
    public function ejecutar(bool $esAdministrador): array
    {
        return ['claves' => array_map(fn ($c) => $c->value, $this->autorizador->permisosDe($esAdministrador))];
    }
}
