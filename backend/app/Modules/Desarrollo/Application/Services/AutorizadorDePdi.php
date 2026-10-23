<?php

namespace App\Modules\Desarrollo\Application\Services;

use App\Modules\Desarrollo\Domain\ValueObjects\ClaveDePermisoPdi;

/**
 * Único punto de decisión de los permisos `desarrollo.pdi.*`. Hoy el catálogo se asigna por rol: el administrador
 * recibe las siete claves de gestión y el colaborador solo las dos terminadas en `_propio`. Cuando exista administración
 * de roles y permisos, solo cambia la fuente de este servicio.
 */
final class AutorizadorDePdi
{
    /** @return list<ClaveDePermisoPdi> */
    public function permisosDe(bool $esAdministrador): array
    {
        return $esAdministrador ? ClaveDePermisoPdi::deAdministrador() : ClaveDePermisoPdi::deColaborador();
    }

    public function permite(bool $esAdministrador, ClaveDePermisoPdi $clave): bool
    {
        return in_array($clave, $this->permisosDe($esAdministrador), true);
    }
}
