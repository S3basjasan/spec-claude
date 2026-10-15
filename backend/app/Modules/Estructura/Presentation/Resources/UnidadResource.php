<?php

namespace App\Modules\Estructura\Presentation\Resources;

use App\Modules\Estructura\Application\DTO\UnidadDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Unidad en el listado.
 *
 * @property UnidadDTO $resource
 */
class UnidadResource extends JsonResource
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
            'tipo' => $this->resource->tipo,
            'unidad_padre' => $this->resource->unidadPadre,
            'nivel_arbol' => $this->resource->nivelArbol,
            'estado' => $this->resource->estado,
            'tiene_dependientes' => $this->resource->tieneDependientes,
            'dependientes_activas' => $this->resource->dependientesActivas,
            'posiciones_vigentes' => $this->resource->posicionesVigentes,
            'admite_hijas' => $this->resource->admiteHijas,
            'sigue_cadena' => $this->resource->sigueCadena,
        ];
    }
}
