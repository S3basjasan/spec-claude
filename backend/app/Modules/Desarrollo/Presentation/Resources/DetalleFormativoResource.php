<?php

namespace App\Modules\Desarrollo\Presentation\Resources;

use App\Modules\Desarrollo\Application\DTO\DetalleFormativoDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property DetalleFormativoDTO $resource */
class DetalleFormativoResource extends JsonResource
{
    /**
     * Administrador: versión y última configuración. Colaborador: nivel actual y requerido (si hay brecha).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $d = $this->resource;

        $detalle = [
            'competencia' => $d->competencia,
            'area_formativa' => $d->area === null ? null : (new AreaFormativaResource($d->area))->toArray($request),
            'cursos' => RecursoResource::collection(collect($d->cursos))->resolve($request),
            'microcontenidos' => RecursoResource::collection(collect($d->microcontenidos))->resolve($request),
        ];

        if ($d->esAdmin) {
            $detalle['version'] = $d->version;
            $detalle['ultima_configuracion'] = $d->ultimaConfiguracion;
        } else {
            $detalle['nivel_actual'] = $d->nivelActual;
            $detalle['nivel_requerido'] = $d->nivelRequerido;
        }

        return $detalle;
    }
}
