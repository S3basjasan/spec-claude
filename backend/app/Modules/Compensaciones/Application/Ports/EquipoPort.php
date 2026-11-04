<?php

namespace App\Modules\Compensaciones\Application\Ports;

/**
 * Quién lidera a quién. Hoy lo resuelve un adaptador provisional basado en configuración del repositorio de
 * prueba; cuando Gestión de Personal publique líder y equipo se cambia solo el enlace del proveedor de servicios.
 */
interface EquipoPort
{
    public function esLider(int $colaboradorId): bool;

    /**
     * @return array<int, int> ids de los colaboradores del equipo del líder
     */
    public function equipoDe(int $liderId): array;

    /** Id del líder del colaborador, o null si no tiene. */
    public function liderDe(int $colaboradorId): ?int;

    /**
     * @param  array<int, int>  $colaboradorIds
     * @return array<int, int> colaborador_id => usuario_id (solo los que tienen usuario)
     */
    public function usuariosDe(array $colaboradorIds): array;
}
