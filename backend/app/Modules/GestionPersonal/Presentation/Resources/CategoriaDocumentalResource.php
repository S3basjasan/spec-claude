<?php

namespace App\Modules\GestionPersonal\Presentation\Resources;

use App\Modules\GestionPersonal\Domain\Entities\CategoriaDocumental;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property CategoriaDocumental $resource
 */
class CategoriaDocumentalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $c = $this->resource;

        return [
            'id' => $c->id,
            'nombre' => $c->nombre,
            'estado' => $c->estado,
            'version' => $c->version,
            'created_by' => $c->createdBy,
            'updated_by' => $c->updatedBy,
            'created_at' => $c->createdAt,
            'updated_at' => $c->updatedAt,
        ];
    }
}
