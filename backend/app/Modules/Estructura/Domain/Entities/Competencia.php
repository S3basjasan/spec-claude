<?php

namespace App\Modules\Estructura\Domain\Entities;

use App\Modules\Estructura\Domain\Services\ReglasDeCompletitud;
use App\Modules\Estructura\Domain\ValueObjects\EscalaDeGrados;
use App\Modules\Estructura\Domain\ValueObjects\EstadoCompetencia;
use App\Modules\Estructura\Domain\ValueObjects\GradoDeCompetencia;
use App\Modules\Estructura\Domain\ValueObjects\TipoCompetencia;

final class Competencia
{
    /**
     * @param  array<string, string>  $grados  Descripción por clave posicional (A–D).
     * @param  array<int, GradoDeCompetencia>  $gradosDetalle  Grados ordenados del más alto al más básico.
     * @param  array<int, string>  $preguntas  Textos en su orden (hasta cuatro).
     * @param  array<int, string>|null  $categorias  Nombres propios por orden (1 a 4) o null si no tiene.
     */
    public function __construct(
        public readonly int $id,
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly string $descripcion,
        public readonly TipoCompetencia $tipo,
        public readonly EstadoCompetencia $estado,
        public readonly int $version,
        public readonly ?int $creadoPor = null,
        public readonly ?int $modificadoPor = null,
        public readonly array $grados = [],
        public readonly ?EscalaDeGrados $escala = null,
        public readonly array $gradosDetalle = [],
        public readonly array $preguntas = [],
        public readonly ?array $categorias = null,
        public readonly ?string $creadaEn = null,
        public readonly ?string $modificadaEn = null,
    ) {}

    public function estaActiva(): bool
    {
        return $this->estado === EstadoCompetencia::Activo;
    }

    /**
     * @return array<int, string> Lo que falta: `grados`, `comportamientos` y/o `preguntas`.
     */
    public function faltantes(): array
    {
        return ReglasDeCompletitud::faltantes($this);
    }

    public function estaCompleta(): bool
    {
        return $this->faltantes() === [];
    }
}
