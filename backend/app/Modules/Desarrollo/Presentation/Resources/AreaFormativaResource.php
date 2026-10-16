<?php

namespace App\Modules\Desarrollo\Presentation\Resources;

use App\Modules\Desarrollo\Application\DTO\AreaDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property AreaDTO $resource */
class AreaFormativaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['id' => $this->resource->id, 'nombre' => $this->resource->nombre];
    }
}
