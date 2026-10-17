<?php

namespace App\Modules\Desarrollo\Presentation\Resources;

use App\Modules\Desarrollo\Application\DTO\MicroDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Detalle de un micro-contenido. La opción correcta, las completaciones y los datos de auditoría solo salen para el
 * Administrador; el colaborador recibe su progreso.
 *
 * @property MicroDTO $resource
 */
class MicroResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $d = $this->resource;
        $m = $d->micro;

        $datos = [
            'id' => $m->id,
            'codigo' => $m->codigo,
            'titulo' => $m->titulo,
            'tipo' => $m->tipo->value,
            'estado' => $m->estado->value,
            'url' => $m->url,
            'miniatura_url' => $m->miniaturaUrl,
            'duracion_minutos' => $m->duracionMinutos,
            'competencias' => $d->competencias,
            'apto_conocimientos' => true,
        ];

        if ($m->tipo->esQuiz()) {
            $datos['preguntas'] = array_map(fn (array $p) => [
                'id' => $p['id'],
                'enunciado' => $p['enunciado'],
                'orden' => $p['orden'],
                'opciones' => array_map(function (array $o) use ($d) {
                    $opcion = ['id' => $o['id'], 'texto' => $o['texto'], 'orden' => $o['orden']];
                    if ($d->esAdmin) {
                        $opcion['es_correcta'] = $o['es_correcta'];
                    }

                    return $opcion;
                }, $p['opciones']),
            ], $m->preguntas);
        }

        if ($d->esAdmin) {
            $datos += [
                'completaciones' => $m->completaciones,
                'puede_eliminarse' => ! $m->tieneProgreso,
                'tiene_intentos' => $m->tieneIntentos,
                'created_by' => $d->creadoPor,
                'created_at' => $m->creadoEn,
                'updated_by' => $d->modificadoPor,
                'updated_at' => $m->modificadoEn,
            ];
        } else {
            $datos['progreso'] = $d->progreso;
        }

        return $datos;
    }
}
