<?php

namespace App\Modules\Compensaciones\Domain\ValueObjects;

/**
 * Forma normalizada de un texto: sin distinguir mayúsculas, tildes ni espacios exteriores o repetidos.
 * Es la base de la unicidad de nombres de categoría y de textos de indicador.
 */
final class Normalizador
{
    private const TILDES = [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
    ];

    public static function normalizar(string $texto): string
    {
        $recortado = trim((string) preg_replace('/\s+/u', ' ', $texto));

        return strtr(mb_strtolower($recortado), self::TILDES);
    }

    /** Recorta los espacios exteriores (el texto plano conserva su capitalización y tildes). */
    public static function recortar(string $texto): string
    {
        return trim($texto);
    }

    public static function contieneHtml(string $texto): bool
    {
        return preg_match('/<\/?[a-z!][^>]*>/i', $texto) === 1;
    }
}
