<?php

namespace App\Modules\GestionPersonal\Presentation\Resources;

use App\Modules\GestionPersonal\Application\DTO\PoliticaResumenDTO;
use App\Modules\GestionPersonal\Application\DTO\VersionDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property PoliticaResumenDTO $resource
 */
class PoliticaResumenResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $r = $this->resource;

        return [
            'tipo' => $r->tipo->identificador(),
            'nombre' => $r->tipo->value,
            'categoria' => $r->tipo->categoria(),
            'situacion' => $r->situacion,
            'disponible' => $r->disponible,
            'version_vigente' => $this->corta($r->vigente),
            'version_programada' => $this->corta($r->programada),
            'tiene_borrador' => $r->borrador !== null,
            'borrador_id' => $r->borrador?->version->id,
        ];
    }

    /** @return array<string, mixed>|null */
    private function corta(?VersionDTO $dto): ?array
    {
        return $dto === null ? null : [
            'id' => $dto->version->id,
            'numero' => $dto->version->numero,
            'inicio_vigencia' => VersionResource::iso($dto->version->inicioVigencia),
            'inicio_vigencia_local' => $dto->inicioLocal,
        ];
    }
}
