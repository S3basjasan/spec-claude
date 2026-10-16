<?php

namespace App\Modules\Estructura\Presentation\Resources;

use App\Modules\Estructura\Application\DTO\OpcionesDePosicionDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Datos de los selectores del formulario de posiciones.
 *
 * @property OpcionesDePosicionDTO $resource
 */
class OpcionesDePosicionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'puestos' => $this->resource->puestos,
            'unidades' => $this->resource->unidades,
            'posiciones' => $this->resource->posiciones,
            'hay_maxima_autoridad' => $this->resource->hayMaximaAutoridad,
            'unidades_minimas' => $this->resource->unidadesMinimas,
        ];
    }
}
