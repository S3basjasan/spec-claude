<?php

namespace App\Modules\Estructura\Presentation\Resources;

use App\Modules\Estructura\Application\DTO\RegistroComportamientosDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property RegistroComportamientosDTO $resource
 */
class RegistroComportamientosDetalleResource extends JsonResource
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
            'descripcion' => $this->resource->descripcion,
            'tipo' => $this->resource->tipo,
            'estado' => $this->resource->estado,
            'version' => $this->resource->version,
            'comportamientos' => $this->resource->comportamientos,
        ];
    }
}
