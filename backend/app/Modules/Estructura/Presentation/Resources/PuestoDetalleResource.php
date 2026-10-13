<?php

namespace App\Modules\Estructura\Presentation\Resources;

use App\Modules\Estructura\Application\DTO\PuestoDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property PuestoDTO $resource
 */
class PuestoDetalleResource extends JsonResource
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
            'mision' => $this->resource->mision,
            'estado' => $this->resource->estado,
            'version' => $this->resource->version,
            'funciones' => $this->resource->funciones,
            'requisitos' => $this->resource->requisitos,
            'competencias' => $this->resource->competencias,
        ];
    }
}
