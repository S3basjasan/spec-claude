<?php

namespace App\Modules\Desarrollo\Presentation\Resources;

use App\Modules\Desarrollo\Domain\Entities\IntentoQuiz;
use App\Modules\Desarrollo\Domain\Entities\Microcontenido;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Intento de un quiz con sus preguntas **sin** la opción correcta: el servidor solo la revela al responder.
 *
 * @property array{intento: IntentoQuiz, micro: Microcontenido} $resource
 */
class IntentoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $intento = $this->resource['intento'];
        $micro = $this->resource['micro'];

        return [
            'id' => $intento->id,
            'estado' => $intento->estado->value,
            'total_preguntas' => $intento->totalPreguntas,
            'respondidas' => $intento->respondidas,
            'preguntas' => array_map(fn (array $p) => [
                'id' => $p['id'],
                'enunciado' => $p['enunciado'],
                'orden' => $p['orden'],
                'opciones' => array_map(fn (array $o) => ['id' => $o['id'], 'texto' => $o['texto'], 'orden' => $o['orden']], $p['opciones']),
            ], $micro->preguntas),
        ];
    }
}
