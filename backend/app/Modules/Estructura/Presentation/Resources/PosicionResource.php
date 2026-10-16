<?php

namespace App\Modules\Estructura\Presentation\Resources;

use App\Modules\Estructura\Application\DTO\PosicionDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Posición en el listado.
 *
 * @property PosicionDTO $resource
 */
class PosicionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'codigo' => $this->resource->codigo,
            'estado' => $this->resource->estado,
            'modalidad' => $this->resource->modalidad,
            'puesto' => $this->resource->puesto,
            'unidad' => $this->resource->unidad,
            'supervisor' => $this->resource->supervisor,
            'ocupante' => $this->resource->ocupante,
            'subordinadas_vigentes' => $this->resource->subordinadasVigentes,
        ];
    }
}
