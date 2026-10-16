<?php

namespace App\Modules\Desarrollo\Application\Ports;

/** Usuarios con rol Administrador que pueden ser aprobadores de un plan (los aporta el módulo Shared). */
interface AprobadoresInterface
{
    /**
     * @return list<array{id: int, nombre: string}>
     */
    public function administradores(int $tenantId): array;

    public function esAdministradorActivo(int $tenantId, int $usuarioId): bool;
}
