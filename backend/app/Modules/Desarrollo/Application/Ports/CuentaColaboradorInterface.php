<?php

namespace App\Modules\Desarrollo\Application\Ports;

/**
 * Ficha de colaborador asociada a la cuenta de un usuario (Gestión de Personal).
 */
interface CuentaColaboradorInterface
{
    /**
     * @return array{tenant_id: int, colaborador_id: int}|null nulo si el usuario no tiene ficha asociada
     */
    public function cuentaDe(int $usuarioId): ?array;
}
