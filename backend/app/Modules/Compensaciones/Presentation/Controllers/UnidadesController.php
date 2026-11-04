<?php

namespace App\Modules\Compensaciones\Presentation\Controllers;

use App\Modules\Compensaciones\Application\UseCases\ListarUnidadesDisponibles;
use Illuminate\Http\JsonResponse;

class UnidadesController
{
    /** Unidades activas para el selector de unidades de una categoría (solo Gestor). */
    public function disponibles(ListarUnidadesDisponibles $listar): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                fn (array $u) => ['id' => $u['id'], 'codigo' => $u['codigo'], 'nombre' => $u['nombre']],
                $listar->ejecutar(),
            ),
        ]);
    }
}
