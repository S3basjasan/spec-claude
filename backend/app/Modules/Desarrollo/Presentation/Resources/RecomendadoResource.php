<?php

namespace App\Modules\Desarrollo\Presentation\Resources;

use App\Modules\Desarrollo\Application\DTO\RecomendadoDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property RecomendadoDTO $resource */
class RecomendadoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $r = $this->resource;

        return [
            'competencia_id' => $r->competenciaId,
            'nombre' => $r->nombre,
            'tipo' => $r->tipo,
            'area_formativa' => $r->area === null ? null : ['id' => $r->area->id, 'nombre' => $r->area->nombre],
            'nivel_actual' => $r->nivelActual,
            'nivel_requerido' => $r->nivelRequerido,
            'cursos_count' => $r->cursosCount,
            'microcontenidos_count' => $r->microcontenidosCount,
        ];
    }
}
