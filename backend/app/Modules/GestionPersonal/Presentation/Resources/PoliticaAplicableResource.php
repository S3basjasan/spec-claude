<?php

namespace App\Modules\GestionPersonal\Presentation\Resources;

use App\Modules\GestionPersonal\Application\DTO\PoliticaAplicableDTO;
use App\Modules\GestionPersonal\Domain\Services\CatalogoDePoliticas;
use App\Modules\GestionPersonal\Domain\ValueObjects\TipoDePolitica;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Condiciones aplicables para cualquier usuario autenticado: situación, número, inicio, parámetros y reglas fijas.
 * Nunca incluye descripción interna, motivo, revisión, responsables, versiones, borradores ni programadas.
 *
 * @property array{tipo: TipoDePolitica, situacion: string, politica: PoliticaAplicableDTO|null} $resource
 */
class PoliticaAplicableResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $tipo = $this->resource['tipo'];
        $politica = $this->resource['politica'];

        return [
            'tipo' => $tipo->identificador(),
            'situacion' => $this->resource['situacion'],
            'numero' => $politica?->numero,
            'inicio_vigencia' => $politica === null ? null : VersionResource::iso($politica->inicioVigencia),
            'parametros' => $politica === null ? null : (object) $politica->parametros,
            'reglas_fijas' => (new CatalogoDePoliticas)->reglasFijas($tipo),
        ];
    }
}
