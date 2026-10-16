<?php

namespace App\Modules\GestionPersonal\Application\Ports;

/** Lectura coherente: todo lo leído dentro del callable pertenece a una misma instantánea de la persistencia. */
interface ContextoDeLecturaPort
{
    /**
     * Ejecuta `$lectura` dentro de una transacción de solo lectura y la cierra al terminar (también si lanza).
     *
     * @template T
     *
     * @param  callable(): T  $lectura
     * @return T
     */
    public function enSnapshot(callable $lectura): mixed;
}
