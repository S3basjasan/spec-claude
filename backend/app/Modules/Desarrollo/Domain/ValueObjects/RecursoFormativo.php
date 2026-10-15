<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

/** Curso o micro-contenido tal como lo ve el catálogo formativo. */
final class RecursoFormativo
{
    public function __construct(
        public readonly int $id,
        public readonly TipoRecurso $tipoRecurso,
        public readonly string $nombre,
        public readonly string $tipo,
        public readonly int $duracionMinutos,
        public readonly ?string $formador,
        public readonly EstadoRecurso $estado,
        public readonly ?string $urlDestino,
        public readonly int $competenciasCount = 0,
    ) {}
}
