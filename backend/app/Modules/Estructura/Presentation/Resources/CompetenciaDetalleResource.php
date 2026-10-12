<?php

namespace App\Modules\Estructura\Presentation\Resources;

use App\Modules\Estructura\Application\DTO\CompetenciaDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property CompetenciaDTO $resource
 */
class CompetenciaDetalleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $grados = [];
        foreach ($this->resource->grados as $letra => $descripcion) {
            $grados[] = ['grado' => $letra, 'descripcion' => $descripcion];
        }

        return [
            'id' => $this->resource->id,
            'codigo' => $this->resource->codigo,
            'nombre' => $this->resource->nombre,
            'descripcion' => $this->resource->descripcion,
            'tipo' => $this->resource->tipo,
            'estado' => $this->resource->estado,
            'version' => $this->resource->version,
            'grados' => $grados,
        ];
    }
}
