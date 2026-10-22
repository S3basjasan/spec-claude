<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Services\AutorizadorDeAdecuacion;

/** Claves de permiso de adecuación del usuario autenticado, para que la interfaz decida qué pestañas y botones mostrar. */
final class ObtenerPermisosDeAdecuacion
{
    public function __construct(private readonly AutorizadorDeAdecuacion $autorizador) {}

    /** @return array{claves: list<string>} */
    public function ejecutar(bool $esAdministrador): array
    {
        return ['claves' => array_map(fn ($c) => $c->value, $this->autorizador->permisosDe($esAdministrador))];
    }
}
