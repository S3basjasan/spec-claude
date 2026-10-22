<?php

namespace App\Modules\GestionPersonal\Presentation\Resources;

use App\Modules\GestionPersonal\Application\DTO\VersionDTO;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property VersionDTO $resource
 */
class VersionResource extends JsonResource
{
    public static function iso(?DateTimeImmutable $fecha): ?string
    {
        return $fecha?->format('Y-m-d\TH:i:s\Z');
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $dto = $this->resource;
        $v = $dto->version;

        return [
            'id' => $v->id,
            'numero' => $v->numero,
            'estado' => $dto->estado->value,
            'descripcion' => $v->descripcion,
            'parametros' => (object) $v->parametros,
            'inicio_vigencia' => self::iso($v->inicioVigencia),
            'inicio_vigencia_local' => $dto->inicioLocal,
            'motivo_cambio' => $v->motivoCambio,
            'revision' => $v->revisorNombre === null ? null : [
                'revisor' => $v->revisorNombre,
                'observaciones' => $v->revisionObservaciones,
                'revision_en' => self::iso($v->revisionEn),
            ],
            'publicada_por' => $v->publicadaPor,
            'publicada_en' => self::iso($v->publicadaEn),
            'retiro' => $v->retiradaEn === null ? null : [
                'por' => $v->retiradaPor,
                'en' => self::iso($v->retiradaEn),
                'motivo' => $v->retiroMotivo,
            ],
            'base_version_id' => $v->baseVersionId,
            'version' => $v->version,
            'created_by' => $v->createdBy,
            'created_at' => self::iso($v->createdAt),
            'updated_by' => $v->updatedBy,
            'updated_at' => self::iso($v->updatedAt),
        ];
    }
}
