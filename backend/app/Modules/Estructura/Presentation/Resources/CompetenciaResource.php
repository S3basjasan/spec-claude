<?php

namespace App\Modules\Estructura\Presentation\Resources;

use App\Modules\Estructura\Application\DTO\CompetenciaDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Elemento del listado: sin grados, comportamientos ni preguntas.
 *
 * @property CompetenciaDTO $resource
 */
class CompetenciaResource extends JsonResource
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
            'estado' => $this->resource->estado,
            'completa' => $this->resource->completa,
        ];
    }
}
