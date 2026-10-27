<?php

namespace App\Modules\GestionPersonal\Application\Ports;

/**
 * Coordinación del tratamiento de la cuenta del colaborador con Identidad y Acceso tras una salida efectiva. La
 * finalización del vínculo nunca se presenta como cuenta deshabilitada: solo `Coordinada` lo afirma, y únicamente
 * cuando el componente propietario lo confirma.
 */
interface CoordinadorDeCuentaPort
{
    /**
     * @return 'Pendiente'|'Coordinada'
     *
     * @throws \Throwable Cualquier fallo se registra como incidencia de la cuenta, sin revertir la salida.
     */
    public function solicitar(int $tenantId, int $colaboradorId, string $fechaEfectiva): string;
}
