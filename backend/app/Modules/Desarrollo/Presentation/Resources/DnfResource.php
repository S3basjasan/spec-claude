<?php

namespace App\Modules\Desarrollo\Presentation\Resources;

use App\Modules\Desarrollo\Application\DTO\DnfDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property DnfDTO $resource
 */
class DnfResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->resource->toArray();
    }
}
