<?php

namespace App\Modules\GestionPersonal\Application\Ports;

/** Transacción corta de escritura sobre la persistencia del módulo. */
interface TransaccionPort
{
    /**
     * Ejecuta `$accion` en una transacción: confirma si termina y revierte si lanza.
     *
     * @template T
     *
     * @param  callable(): T  $accion
     * @return T
     */
    public function enTransaccion(callable $accion): mixed;
}
