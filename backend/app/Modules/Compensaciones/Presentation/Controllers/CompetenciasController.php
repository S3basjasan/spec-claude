<?php

namespace App\Modules\Compensaciones\Presentation\Controllers;

use App\Modules\Compensaciones\Application\Services\PoliticaDeAcceso;
use App\Modules\Compensaciones\Application\UseCases\ListarCompetenciasDisponibles;
use App\Modules\Compensaciones\Application\UseCases\ListarCompetenciasParaFiltro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Opciones de competencia para el formulario (solo Administrador) y para el filtro del listado (todos).
 */
class CompetenciasController
{
    public function __construct(private readonly PoliticaDeAcceso $acceso) {}

    public function disponibles(ListarCompetenciasDisponibles $listar): JsonResponse
    {
        return response()->json(['data' => $listar->ejecutar()]);
    }

    public function filtro(Request $request, ListarCompetenciasParaFiltro $listar): JsonResponse
    {
        return response()->json(['data' => $listar->ejecutar($this->acceso->puedeGestionar($request->user()))]);
    }
}
