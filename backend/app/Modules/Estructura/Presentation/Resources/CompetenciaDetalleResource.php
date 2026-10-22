<?php

namespace App\Modules\Estructura\Presentation\Resources;

use App\Modules\Estructura\Application\DTO\CompetenciaDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property CompetenciaDTO $resource
 */
class CompetenciaDetalleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $detalle = [
            'id' => $this->resource->id,
            'codigo' => $this->resource->codigo,
            'nombre' => $this->resource->nombre,
            'descripcion' => $this->resource->descripcion,
            'tipo' => $this->resource->tipo,
            'estado' => $this->resource->estado,
            'version' => $this->resource->version,
            'completa' => $this->resource->completa,
            'faltantes' => $this->resource->faltantes,
            'escala' => $this->resource->escala,
            'categorias' => $this->resource->categorias,
            'grados' => array_map(fn (array $grado) => [
                'id' => $grado['id'],
                'orden' => $grado['orden'],
                'etiqueta' => $grado['etiqueta'],
                'descripcion' => $grado['descripcion'],
                'comportamientos' => $grado['comportamientos'],
            ], $this->resource->gradosDetalle),
            'preguntas' => $this->resource->preguntas,
        ];

        // La auditoría (quién y cuándo) solo la recibe el administrador.
        if ($this->resource->auditoria !== null) {
            $detalle['auditoria'] = $this->resource->auditoria;
        }

        return $detalle;
    }
}
