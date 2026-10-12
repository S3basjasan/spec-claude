<?php

namespace App\Modules\Shared\Presentation\Resources;

use App\Modules\Shared\Application\DTO\UsuarioDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property UsuarioDTO $resource
 */
class UsuarioResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->nombre,
            'email' => $this->resource->correo,
            'role' => $this->resource->rol,
        ];
    }
}
