<?php

namespace App\Modules\GestionPersonal\Presentation\Resources;

use App\Modules\GestionPersonal\Application\DTO\DocumentoConCategoriaDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representación del Colaborador: sin visibilidad, estado, auditoría ni claves de almacenamiento.
 *
 * @property DocumentoConCategoriaDTO $resource
 */
class MiDocumentoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $d = $this->resource->documento;

        return [
            'id' => $d->id,
            'titulo' => $d->titulo,
            'categoria' => $this->resource->categoria,
            'formato' => $d->mimeType,
            'tamano_bytes' => $d->sizeBytes,
            'created_at' => $d->createdAt,
        ];
    }
}
