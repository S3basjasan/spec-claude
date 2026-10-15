<?php

namespace App\Modules\Estructura\Presentation\Resources;

use App\Modules\Estructura\Application\DTO\OpcionesDeUnidadDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Datos de los selectores de los formularios de unidades.
 *
 * @property OpcionesDeUnidadDTO $resource
 */
class OpcionesDeUnidadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'tipos' => $this->resource->tipos,
            'unidades' => $this->resource->unidades,
        ];
    }
}
