<?php

namespace App\Modules\Estructura\Presentation\Resources;

use App\Modules\Estructura\Application\DTO\CompetenciaDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property CompetenciaDTO $resource
 */
class CompetenciaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'codigo' => $this->resource->codigo,
            'nombre' => $this->resource->nombre,
            'descripcion' => $this->resource->descripcion,
            'tipo' => $this->resource->tipo,
            'estado' => $this->resource->estado,
        ];
    }
}
