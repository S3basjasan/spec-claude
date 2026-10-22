<?php

namespace App\Modules\Desarrollo\Domain\Exceptions;

/** Tiempo estimado de cierre faltante en una brecha, fuera de rango o enviado para un resultado que no es brecha. */
final class TiempoDeCierreInvalido extends ErrorDeAdecuacion
{
    public const MAXIMO_MESES = 60;

    /** @param  list<string>  $claves  claves `dimension:elemento` de las brechas sin tiempo */
    public static function faltante(array $claves): self
    {
        $errores = [];
        foreach ($claves as $i => $clave) {
            $errores["tiempos_cierre.{$clave}"] = ['Ingresa el tiempo estimado de cierre en meses.'];
        }

        return new self('Ingresa el tiempo estimado de cierre de cada brecha.', 'tiempo_cierre_requerido', null, $errores);
    }

    public static function fueraDeRango(string $clave): self
    {
        return new self('El tiempo de cierre debe ser un entero entre 1 y '.self::MAXIMO_MESES.' meses.', 'tiempo_cierre_requerido', null, ["tiempos_cierre.{$clave}" => ['Debe ser un entero entre 1 y '.self::MAXIMO_MESES.'.']]);
    }

    public static function noEsBrecha(string $clave): self
    {
        return new self('Solo las brechas llevan tiempo de cierre.', 'tiempo_cierre_requerido', null, ["tiempos_cierre.{$clave}" => ['Este requisito no es una brecha.']]);
    }
}
