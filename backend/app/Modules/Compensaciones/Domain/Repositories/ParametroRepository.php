<?php

namespace App\Modules\Compensaciones\Domain\Repositories;

use App\Modules\Compensaciones\Domain\Entities\CambioDeParametro;
use App\Modules\Compensaciones\Domain\ValueObjects\ClaveDeParametro;
use DateTimeImmutable;

/**
 * Historial de parámetros del módulo. Solo se inserta: no hay operación de actualizar ni de borrar.
 */
interface ParametroRepository
{
    /**
     * Ejecuta la acción en una transacción y serializa las escrituras de parámetros, de modo que la comprobación
     * de `version` y la inserción no se crucen con las de otra persona.
     *
     * @template T
     *
     * @param  callable(): T  $accion
     * @return T
     */
    public function enTransaccion(callable $accion): mixed;

    /** Última fila de la clave (la de mayor id), o null si nunca cambió. */
    public function ultima(ClaveDeParametro $clave): ?CambioDeParametro;

    /** Fila con `vigente_desde` más reciente que no supera la fecha; con empate, la de mayor id. */
    public function vigenteEn(ClaveDeParametro $clave, string $fecha): ?CambioDeParametro;

    /** Fila de mayor id con `vigente_desde` posterior a la fecha, o null. */
    public function programadaDespuesDe(ClaveDeParametro $clave, string $fecha): ?CambioDeParametro;

    /** Fila anterior de la misma clave (mayor id menor que el dado), o null si el dado es el primer cambio. */
    public function anteriorA(CambioDeParametro $cambio): ?CambioDeParametro;

    public function insertar(ClaveDeParametro $clave, int $valor, string $vigenteDesde, int $actorId, DateTimeImmutable $ahora): CambioDeParametro;
}
