<?php

namespace App\Modules\Estructura\Presentation\Resources;

use App\Modules\Estructura\Application\DTO\ImpactoDesactivacionDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Impacto de desactivar una unidad: dependientes activas y unidades con posiciones vigentes.
 *
 * @property ImpactoDesactivacionDTO $resource
 */
class ImpactoDesactivacionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'procede' => $this->resource->procede,
            'dependientes_activas' => $this->resource->dependientesActivas,
            'unidades_con_posiciones' => $this->resource->unidadesConPosiciones,
        ];
    }
}
