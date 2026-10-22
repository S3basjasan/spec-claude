<?php

namespace App\Modules\GestionPersonal\Presentation\Resources;

use App\Modules\GestionPersonal\Application\DTO\PoliticaDetalleDTO;
use App\Modules\GestionPersonal\Application\DTO\VersionDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property PoliticaDetalleDTO $resource
 */
class PoliticaDetalleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $d = $this->resource;

        return [
            'resumen' => (new PoliticaResumenResource($d->resumen))->toArray($request),
            'definicion' => $d->definicion,
            'vigente' => $this->version($d->resumen->vigente, $request),
            'programada' => $this->version($d->resumen->programada, $request),
            'borrador' => $this->version($d->resumen->borrador, $request),
            'versiones' => array_map(fn (VersionDTO $v) => (new VersionResource($v))->toArray($request), $d->versiones),
        ];
    }

    /** @return array<string, mixed>|null */
    private function version(?VersionDTO $dto, Request $request): ?array
    {
        return $dto === null ? null : (new VersionResource($dto))->toArray($request);
    }
}
