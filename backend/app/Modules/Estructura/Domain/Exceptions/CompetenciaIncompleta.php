<?php

namespace App\Modules\Estructura\Domain\Exceptions;

use RuntimeException;

final class CompetenciaIncompleta extends RuntimeException
{
    /**
     * @param  array<int, string>  $faltantes  `grados`, `comportamientos` y/o `preguntas`.
     */
    public function __construct(public readonly array $faltantes)
    {
        parent::__construct('No se puede activar: faltan '.self::lista($faltantes).'.');
    }

    /**
     * @param  array<int, string>  $faltantes
     */
    private static function lista(array $faltantes): string
    {
        $nombres = array_map(fn (string $f) => match ($f) {
            'grados' => 'los grados',
            'comportamientos' => 'los comportamientos',
            'preguntas' => 'las preguntas',
            default => $f,
        }, $faltantes);

        if (count($nombres) <= 1) {
            return $nombres[0] ?? 'datos';
        }

        $ultimo = array_pop($nombres);

        return implode(', ', $nombres).' y '.$ultimo;
    }
}
