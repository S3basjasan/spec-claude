<?php

namespace App\Modules\Desarrollo\Domain\Services;

/** Forma comparable de un nombre de plan: sin espacios laterales ni repetidos y sin distinguir mayúsculas. */
final class NormalizadorDeNombre
{
    public function normalizar(string $nombre): string
    {
        $limpio = preg_replace('/\s+/u', ' ', trim($nombre)) ?? trim($nombre);

        return mb_strtolower($limpio);
    }

    /** Nombre tal como se guarda para mostrar: sin espacios laterales ni repetidos. */
    public function limpiar(string $nombre): string
    {
        return preg_replace('/\s+/u', ' ', trim($nombre)) ?? trim($nombre);
    }
}
