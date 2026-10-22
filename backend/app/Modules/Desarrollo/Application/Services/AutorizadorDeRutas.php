<?php

namespace App\Modules\Desarrollo\Application\Services;

use App\Modules\Desarrollo\Domain\ValueObjects\ClaveDePermisoRutas;

/**
 * Único punto de decisión de los permisos `desarrollo.rutas.*`. Hoy el catálogo se asigna por rol: el administrador recibe
 * las claves de gestión y consulta, y el colaborador solo las terminadas en `_propio`. Cuando exista administración de
 * roles y permisos, solo cambia la fuente de este servicio.
 */
final class AutorizadorDeRutas
{
    /** @return list<ClaveDePermisoRutas> */
    public function permisosDe(bool $esAdministrador): array
    {
        return $esAdministrador ? ClaveDePermisoRutas::deAdministrador() : ClaveDePermisoRutas::deColaborador();
    }

    public function permite(bool $esAdministrador, ClaveDePermisoRutas $clave): bool
    {
        return in_array($clave, $this->permisosDe($esAdministrador), true);
    }
}
