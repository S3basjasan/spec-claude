<?php

namespace App\Modules\Estructura\Presentation\Resources;

use App\Modules\Estructura\Application\DTO\PosicionDetalleDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Detalle de la posición. `auditoria` solo se incluye cuando el caso de uso la informó (Administrador).
 *
 * @property PosicionDetalleDTO $resource
 */
class PosicionDetalleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $datos = [
            'id' => $this->resource->id,
            'codigo' => $this->resource->codigo,
            'estado' => $this->resource->estado,
            'modalidad' => $this->resource->modalidad,
            'puesto' => $this->resource->puesto,
            'unidad' => $this->resource->unidad,
            'supervisor' => $this->resource->supervisor,
            'subordinadas_vigentes' => $this->resource->subordinadasVigentes,
            'version' => $this->resource->version,
            'ocupante' => $this->resource->ocupante,
            'subordinadas' => $this->resource->subordinadas,
        ];

        if ($this->resource->auditoria !== null) {
            $datos['auditoria'] = $this->resource->auditoria;
        }

        return $datos;
    }
}
