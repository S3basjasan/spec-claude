<?php

namespace App\Modules\Desarrollo\Presentation\Resources;

use App\Modules\Desarrollo\Application\DTO\RecursoDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property RecursoDTO $resource */
class RecursoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $r = $this->resource;

        return array_filter([
            'id' => $r->id,
            'nombre' => $r->nombre,
            'tipo' => $r->tipo,
            'duracion_minutos' => $r->duracionMinutos,
            'formador' => $r->formador,
            'estado' => $r->estado,
            'accion' => $r->accion,
            'url_destino' => $r->urlDestino,
            'competencias_count' => $r->competenciasCount,
        ], fn ($valor, $clave) => $clave !== 'competencias_count' || $valor !== null, ARRAY_FILTER_USE_BOTH);
    }
}
