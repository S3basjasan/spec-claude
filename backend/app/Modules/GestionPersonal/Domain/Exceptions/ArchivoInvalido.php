<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

/** El archivo no se acepta; `motivo` es `vacio`, `demasiado_grande`, `no_es_pdf` o `estructura_invalida`. */
final class ArchivoInvalido extends RuntimeException
{
    public const MENSAJES = [
        'vacio' => 'El archivo está vacío.',
        'demasiado_grande' => 'El archivo supera el tamaño máximo de 10 MB.',
        'no_es_pdf' => 'El formato no es válido. Selecciona un archivo PDF.',
        'estructura_invalida' => 'El archivo no es un PDF válido o está dañado.',
    ];

    public function __construct(public readonly string $motivo)
    {
        parent::__construct(self::MENSAJES[$motivo] ?? 'El archivo no es válido.');
    }
}
