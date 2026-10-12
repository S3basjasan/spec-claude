<?php

namespace App\Modules\Shared\Presentation\Resources;

use App\Modules\Shared\Application\DTO\SesionDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property SesionDTO $resource
 */
class SesionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->resource->token,
            'user' => new UsuarioResource($this->resource->usuario),
        ];
    }
}
