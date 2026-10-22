<?php

namespace App\Modules\Estructura\Presentation\Resources;

use App\Modules\Estructura\Application\DTO\NodoDeOrganigramaDTO;
use App\Modules\Estructura\Application\DTO\OrganigramaDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Organigrama: dos listas planas (posiciones vigentes y unidades activas). Sin auditoría ni datos de cuentas.
 *
 * @property OrganigramaDTO $resource
 */
class OrganigramaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'posiciones' => array_map(fn (NodoDeOrganigramaDTO $nodo) => [
                'id' => $nodo->id,
                'codigo' => $nodo->codigo,
                'estado' => $nodo->estado,
                'modalidad' => $nodo->modalidad,
                'posicion_jefe_id' => $nodo->posicionJefeId,
                'puesto' => $nodo->puesto,
                'unidad' => $nodo->unidad,
                'ocupante' => $nodo->ocupante,
            ], $this->resource->posiciones),
            'unidades' => $this->resource->unidades,
        ];
    }
}
