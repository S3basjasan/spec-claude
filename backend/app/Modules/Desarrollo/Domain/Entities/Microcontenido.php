<?php

namespace App\Modules\Desarrollo\Domain\Entities;

use App\Modules\Desarrollo\Domain\ValueObjects\EstadoMicro;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoMicro;

/** Micro-contenido con sus competencias, preguntas y los datos derivados que el Administrador necesita. */
final class Microcontenido
{
    /**
     * @param  array<int, int>  $competenciaIds
     * @param  array<int, array{id: int, enunciado: string, orden: int, opciones: array<int, array{id: int, texto: string, orden: int, es_correcta: bool}>}>  $preguntas
     */
    public function __construct(
        public readonly int $id,
        public readonly string $codigo,
        public readonly string $titulo,
        public readonly TipoMicro $tipo,
        public readonly ?string $url,
        public readonly ?string $miniaturaUrl,
        public readonly int $duracionMinutos,
        public readonly EstadoMicro $estado,
        public readonly array $competenciaIds,
        public readonly array $preguntas,
        public readonly ?int $creadoPor,
        public readonly ?string $creadoEn,
        public readonly ?int $modificadoPor,
        public readonly ?string $modificadoEn,
        public readonly int $completaciones,
        public readonly bool $tieneProgreso,
        public readonly bool $tieneIntentos,
    ) {}
}
