<?php

namespace App\Modules\Estructura\Application\DTO;

use App\Modules\Estructura\Domain\Entities\Competencia;
use App\Modules\Estructura\Domain\ValueObjects\GradoDeCompetencia;

final class CompetenciaDTO
{
    /**
     * @param  array<string, string>  $grados  Descripción por clave posicional A–D.
     * @param  array{tipo_grado_id: int, nombre: string}|null  $escala
     * @param  array<int, array{id: int, orden: int, clave: string, etiqueta: string, descripcion: string, comportamientos: array<int, string>}>  $gradosDetalle
     * @param  array<int, string>  $preguntas
     * @param  array<int, string>|null  $categorias
     * @param  array<int, string>  $faltantes  `grados`, `comportamientos` y/o `preguntas`.
     * @param  array{created_by: ?string, created_at: ?string, updated_by: ?string, updated_at: ?string}|null  $auditoria  Solo para el administrador.
     */
    public function __construct(
        public readonly int $id,
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly string $descripcion,
        public readonly string $tipo,
        public readonly string $estado,
        public readonly int $version,
        public readonly array $grados = [],
        public readonly ?array $escala = null,
        public readonly array $gradosDetalle = [],
        public readonly array $preguntas = [],
        public readonly ?array $categorias = null,
        public readonly bool $completa = true,
        public readonly array $faltantes = [],
        public readonly ?array $auditoria = null,
    ) {}

    /**
     * @param  array{created_by: ?string, created_at: ?string, updated_by: ?string, updated_at: ?string}|null  $auditoria
     */
    public static function desde(Competencia $competencia, ?array $auditoria = null): self
    {
        $faltantes = $competencia->faltantes();

        return new self(
            $competencia->id,
            $competencia->codigo,
            $competencia->nombre,
            $competencia->descripcion,
            $competencia->tipo->value,
            $competencia->estado->value,
            $competencia->version,
            $competencia->grados,
            $competencia->escala === null ? null : ['tipo_grado_id' => $competencia->escala->id, 'nombre' => $competencia->escala->nombre],
            array_map(fn (GradoDeCompetencia $g) => [
                'id' => $g->id,
                'orden' => $g->orden,
                'clave' => $g->clave,
                'etiqueta' => $g->etiqueta,
                'descripcion' => $g->descripcion,
                'comportamientos' => $g->comportamientos,
            ], $competencia->gradosDetalle),
            $competencia->preguntas,
            $competencia->categorias === null ? null : array_values($competencia->categorias),
            $faltantes === [],
            $faltantes,
            $auditoria,
        );
    }
}
