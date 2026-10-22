<?php

namespace App\Modules\Estructura\Domain\Services;

use App\Modules\Estructura\Domain\Exceptions\CategoriasInvalidas;

/**
 * Nombres propios de la escala Categorías: exactamente cuatro, cada uno obligatorio de hasta 20 caracteres,
 * sin vacíos y sin repetirse sin distinguir mayúsculas, tildes ni espacios.
 */
final class ReglasDeCategorias
{
    public const MAXIMO = 20;

    /**
     * @param  array<int, string>  $nombres
     *
     * @throws CategoriasInvalidas
     */
    public static function validar(array $nombres): void
    {
        if (count($nombres) !== 4) {
            throw new CategoriasInvalidas(CategoriasInvalidas::INCOMPLETAS);
        }

        $claves = [];
        foreach ($nombres as $nombre) {
            $limpio = trim($nombre);

            if ($limpio === '') {
                throw new CategoriasInvalidas(CategoriasInvalidas::INCOMPLETAS);
            }

            if (mb_strlen($limpio) > self::MAXIMO) {
                throw new CategoriasInvalidas(CategoriasInvalidas::DEMASIADO_LARGO);
            }

            $claves[] = self::clave($limpio);
        }

        if (count(array_unique($claves)) !== 4) {
            throw new CategoriasInvalidas(CategoriasInvalidas::INCOMPLETAS);
        }
    }

    /** Nombre recortado, en minúsculas y sin tildes. */
    public static function clave(string $nombre): string
    {
        $descompuesto = \Normalizer::normalize(trim($nombre), \Normalizer::FORM_D);

        return mb_strtolower((string) preg_replace('/\p{Mn}/u', '', (string) $descompuesto));
    }
}
