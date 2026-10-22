<?php

namespace App\Modules\GestionPersonal\Presentation\Resources;

use App\Modules\GestionPersonal\Application\DTO\PrevisualizacionDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property PrevisualizacionDTO $resource
 */
class PrevisualizacionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $p = $this->resource;

        return [
            'condiciones' => $p->condiciones,
            'diferencias' => $p->diferencias,
            'inicio_vigencia' => VersionResource::iso($p->inicioVigencia),
            'inicio_vigencia_local' => $p->inicioLocal,
            'efecto' => $p->efecto,
            'errores' => (object) $p->errores,
        ];
    }
}
