<?php

namespace App\Modules\Desarrollo\Presentation\Resources;

use App\Modules\Desarrollo\Application\DTO\MicroDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fila del repositorio. `completaciones` y `puede_eliminarse` solo se envían al Administrador; el colaborador recibe
 * su `progreso`.
 *
 * @property MicroDTO $resource
 */
class FilaMicroResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $d = $this->resource;
        $m = $d->micro;

        $fila = [
            'id' => $m->id,
            'codigo' => $m->codigo,
            'titulo' => $m->titulo,
            'tipo' => $m->tipo->value,
            'estado' => $m->estado->value,
            'miniatura_url' => $m->miniaturaUrl,
            'duracion_minutos' => $m->duracionMinutos,
            'competencias' => $d->competencias,
            'apto_conocimientos' => true,
        ];

        if ($d->esAdmin) {
            $fila['completaciones'] = $m->completaciones;
            $fila['puede_eliminarse'] = ! $m->tieneProgreso;
        } else {
            $fila['progreso'] = $d->progreso;
        }

        return $fila;
    }
}
