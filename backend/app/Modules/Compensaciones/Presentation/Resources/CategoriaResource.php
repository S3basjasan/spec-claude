<?php

namespace App\Modules\Compensaciones\Presentation\Resources;

use App\Modules\Compensaciones\Application\DTOs\CategoriaDTO;
use App\Modules\Compensaciones\Application\Services\PoliticaDeAcceso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property CategoriaDTO $resource
 */
class CategoriaResource extends JsonResource
{
    private bool $resumen = false;

    /** Versión del listado: sin indicadores. */
    public function enResumen(): static
    {
        $this->resumen = true;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $dto = $this->resource;
        $categoria = $dto->categoria;
        $competencia = $dto->competencia;

        $datos = [
            'id' => $categoria->id,
            'codigo' => $categoria->codigo,
            'nombre' => $categoria->nombre->valor,
            'descripcion' => $categoria->descripcion,
            'competencia' => $competencia === null ? null : [
                'id' => $competencia['id'],
                'codigo' => $competencia['codigo'],
                'nombre' => $competencia['nombre'],
                'estado' => $competencia['estado'],
            ],
            'competencia_activa' => $dto->competenciaActiva(),
            'seleccionable' => $dto->seleccionable(),
            'puntuacion_base' => $categoria->puntuacion->valor,
            'icono' => $categoria->icono->value,
            'estado' => $categoria->estado->value,
        ];

        if (! $this->resumen) {
            $datos['indicadores'] = $dto->indicadores();
        }

        $datos['updated_at'] = $categoria->tokenParaCliente();

        // La auditoría solo se muestra a quien puede gestionar el catálogo.
        if ($request->user() !== null && app(PoliticaDeAcceso::class)->puedeGestionar($request->user())) {
            $datos['created_by'] = $categoria->creadaPor;
            $datos['updated_by'] = $categoria->actualizadaPor;
            $datos['created_at'] = $categoria->creadaEn?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
        }

        return $datos;
    }
}
