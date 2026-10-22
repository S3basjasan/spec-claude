<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Services\AutorizadorDeRutas;

/** Claves de permiso de rutas del usuario autenticado, para que la interfaz decida qué botones y secciones mostrar. */
final class ObtenerPermisosDeRutas
{
    public function __construct(private readonly AutorizadorDeRutas $autorizador) {}

    /** @return array{permisos: list<string>} */
    public function ejecutar(bool $esAdministrador): array
    {
        return ['permisos' => array_map(fn ($c) => $c->value, $this->autorizador->permisosDe($esAdministrador))];
    }
}
