<?php

namespace App\Modules\GestionPersonal\Application\DTO;

final class CargaDeDocumentoDTO
{
    public function __construct(
        public readonly mixed $titulo,
        public readonly mixed $categoriaId,
        public readonly bool $visible,
        /** Ruta del archivo recibido en temporal privado. */
        public readonly string $rutaTemporal,
    ) {}
}
