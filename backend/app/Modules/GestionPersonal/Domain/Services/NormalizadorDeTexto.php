<?php

namespace App\Modules\GestionPersonal\Domain\Services;

use Illuminate\Support\Str;
use Normalizer;

final class NormalizadorDeTexto
{
    /** Clave de identificación: NFC, recorte exterior y minúsculas; conserva ceros, separadores y tildes. */
    public function claveDeIdentificacion(string $numero): string
    {
        $texto = trim($numero);
        $nfc = Normalizer::normalize($texto, Normalizer::FORM_C);

        return mb_strtolower($nfc === false ? $texto : $nfc);
    }

    /** Texto de búsqueda: minúsculas, sin tildes y recortado. */
    public function busquedaDeTexto(string $texto): string
    {
        return mb_strtolower(trim(Str::ascii($texto)));
    }
}
