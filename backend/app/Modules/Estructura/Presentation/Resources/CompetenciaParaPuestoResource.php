<?php

namespace App\Modules\Estructura\Presentation\Resources;

use App\Modules\Estructura\Application\DTO\CompetenciaParaPuestoDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property CompetenciaParaPuestoDTO $resource
 */
class CompetenciaParaPuestoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'competencia_id' => $this->resource->competenciaId,
            'codigo' => $this->resource->codigo,
            'nombre' => $this->resource->nombre,
            'tipo' => $this->resource->tipo,
            'grados' => $this->resource->grados,
        ];
    }
}
