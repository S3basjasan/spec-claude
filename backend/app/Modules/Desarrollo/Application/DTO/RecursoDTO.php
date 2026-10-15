<?php

namespace App\Modules\Desarrollo\Application\DTO;

use App\Modules\Desarrollo\Domain\ValueObjects\RecursoFormativo;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoRecurso;

final class RecursoDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $nombre,
        public readonly string $tipo,
        public readonly int $duracionMinutos,
        public readonly ?string $formador,
        public readonly string $estado,
        public readonly string $accion,
        public readonly ?string $urlDestino,
        public readonly ?int $competenciasCount,
    ) {}

    public static function desde(RecursoFormativo $r, bool $conUsos = false): self
    {
        return new self(
            $r->id,
            $r->nombre,
            $r->tipo,
            $r->duracionMinutos,
            $r->formador,
            $r->estado->value,
            $r->tipoRecurso === TipoRecurso::Curso ? 'Iniciar' : 'Ver',
            $r->urlDestino,
            $conUsos ? $r->competenciasCount : null,
        );
    }
}
