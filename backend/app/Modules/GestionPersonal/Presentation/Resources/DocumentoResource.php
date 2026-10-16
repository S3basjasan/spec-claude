<?php

namespace App\Modules\GestionPersonal\Presentation\Resources;

use App\Modules\GestionPersonal\Application\DTO\DocumentoConCategoriaDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representación del Administrador. Nunca incluye `object_key`, rutas, URL ni credenciales de almacenamiento.
 *
 * @property DocumentoConCategoriaDTO $resource
 */
class DocumentoResource extends JsonResource
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
            'estado' => $d->estado,
            'visible_colaborador' => $d->visibleColaborador,
            'categoria' => $this->resource->categoria,
            'formato' => $d->mimeType,
            'tamano_bytes' => $d->sizeBytes,
            'version' => $d->version,
            'created_by' => $d->createdBy,
            'created_at' => $d->createdAt,
            'updated_by' => $d->updatedBy,
            'updated_at' => $d->updatedAt,
            'archivado_por' => $d->archivedBy,
            'archivado_en' => $d->archivedAt,
        ];
    }
}
