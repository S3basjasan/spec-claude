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

    /** Indica si ya hay una transacción abierta (los llamadores que exigen compartir la unidad de trabajo lo comprueban). */
    public function enCurso(): bool;
}
