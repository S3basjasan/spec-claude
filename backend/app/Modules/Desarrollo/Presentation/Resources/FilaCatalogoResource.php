<?php

namespace App\Modules\Desarrollo\Presentation\Resources;

use App\Modules\Desarrollo\Application\DTO\FilaCatalogoDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property FilaCatalogoDTO $resource */
class FilaCatalogoResource extends JsonResource
{
    /**
     * `nivel_actual` solo se incluye para el colaborador.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $f = $this->resource;

        $fila = [
            'competencia_id' => $f->competenciaId,
            'codigo' => $f->codigo,
            'nombre' => $f->nombre,
            'tipo' => $f->tipo,
            'estado' => $f->estado,
            'area_formativa' => $f->areaFormativaId === null ? null : ['id' => $f->areaFormativaId, 'nombre' => $f->areaFormativaNombre],
            'cursos_count' => $f->cursosCount,
            'microcontenidos_count' => $f->microcontenidosCount,
            'cubierta' => $f->cubierta,
        ];

        if ($f->incluyeNivel) {
            $fila['nivel_actual'] = $f->nivelActual;
        }

        return $fila;
    }
}
