<?php

namespace App\Modules\GestionPersonal\Application\DTO;

final class ContenidoDeDocumentoDTO
{
    /** @param resource $flujo Ya abierto: si el almacén falló, nunca se llegó a crear este DTO. */
    public function __construct(
        public readonly mixed $flujo,
        public readonly int $tamano,
        public readonly string $nombreDeArchivo,
    ) {}
}
