<?php

namespace App\Modules\GestionPersonal\Presentation\Resources;

use App\Modules\GestionPersonal\Application\DTO\DesarrolloDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property DesarrolloDTO $resource
 */
class DesarrolloResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->resource->datos;
    }
}
