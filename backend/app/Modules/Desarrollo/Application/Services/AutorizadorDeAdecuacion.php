<?php

namespace App\Modules\Desarrollo\Application\Services;

use App\Modules\Desarrollo\Domain\ValueObjects\ClaveDePermisoAdecuacion;

/**
 * Único punto de decisión de los permisos `desarrollo.adecuacion.*`. Hoy el catálogo se asigna por rol: el administrador
 * recibe las cinco claves de gestión y el colaborador solo `ver_propio`. Cuando exista administración de roles y
 * permisos, solo cambia la fuente de este servicio.
 */
final class AutorizadorDeAdecuacion
{
    /** @return list<ClaveDePermisoAdecuacion> */
    public function permisosDe(bool $esAdministrador): array
    {
        return $esAdministrador ? ClaveDePermisoAdecuacion::deAdministrador() : ClaveDePermisoAdecuacion::deColaborador();
    }

    public function permite(bool $esAdministrador, ClaveDePermisoAdecuacion $clave): bool
    {
        return in_array($clave, $this->permisosDe($esAdministrador), true);
    }
}
