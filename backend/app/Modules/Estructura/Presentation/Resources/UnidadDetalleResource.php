<?php

namespace App\Modules\Estructura\Presentation\Resources;

use App\Modules\Estructura\Application\DTO\UnidadDetalleDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Detalle de la unidad. `auditoria` solo se incluye cuando el caso de uso la informó (Administrador).
 *
 * @property UnidadDetalleDTO $resource
 */
class UnidadDetalleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $datos = [
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
            'version' => $this->resource->version,
            'fecha_creacion' => $this->resource->fechaCreacion,
            'fecha_supresion' => $this->resource->fechaSupresion,
            'dependientes' => $this->resource->dependientes,
        ];

        if ($this->resource->auditoria !== null) {
            $datos['auditoria'] = $this->resource->auditoria;
        }

        return $datos;
    }
}
