<?php

namespace App\Modules\Estructura\Domain\Services;

use Normalizer;

/**
 * Normaliza texto para buscar sin distinguir mayúsculas ni tildes. Puro: sin Laravel.
 */
final class NormalizadorDeTexto
{
    /** Recorta, pasa a minúsculas y quita las marcas diacríticas (`Área` → `area`, `Sección` → `seccion`). */
    public function paraBuscar(string $texto): string
    {
        $descompuesto = Normalizer::normalize(mb_strtolower(trim($texto)), Normalizer::FORM_D);

        return preg_replace('/\p{Mn}+/u', '', $descompuesto === false ? mb_strtolower(trim($texto)) : $descompuesto) ?? '';
    }
}
