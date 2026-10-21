<?php

namespace App\Modules\Estructura\Domain\Services;

use App\Modules\Estructura\Domain\Entities\Competencia;

/**
 * Una competencia está completa si tiene exactamente 4 grados con descripción, 16 comportamientos
 * (4 por grado) y 4 preguntas, todos con texto. Un texto con solo espacios cuenta como vacío.
 */
final class ReglasDeCompletitud
{
    public const GRADOS = 'grados';

    public const COMPORTAMIENTOS = 'comportamientos';

    public const PREGUNTAS = 'preguntas';

    /**
     * @return array<int, string> Lo que falta: `grados`, `comportamientos` y/o `preguntas`.
     */
    public static function faltantes(Competencia $competencia): array
    {
        $faltantes = [];

        $grados = $competencia->gradosDetalle;
        if (count($grados) !== 4 || self::hayVacios(array_map(fn ($g) => $g->descripcion, $grados))) {
            $faltantes[] = self::GRADOS;
        }

        $comportamientosCompletos = count($grados) === 4;
        foreach ($grados as $grado) {
            if (count($grado->comportamientos) !== 4 || self::hayVacios($grado->comportamientos)) {
                $comportamientosCompletos = false;
            }
        }
        if (! $comportamientosCompletos) {
            $faltantes[] = self::COMPORTAMIENTOS;
        }

        if (count($competencia->preguntas) !== 4 || self::hayVacios($competencia->preguntas)) {
            $faltantes[] = self::PREGUNTAS;
        }

        return $faltantes;
    }

    /**
     * @param  array<int, string>  $textos
     */
    private static function hayVacios(array $textos): bool
    {
        foreach ($textos as $texto) {
            if (trim($texto) === '') {
                return true;
            }
        }

        return false;
    }
}
