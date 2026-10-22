<?php

namespace App\Modules\Estructura\Presentation\Controllers;

use App\Modules\Estructura\Application\UseCases\ListarEscalas;
use Illuminate\Http\JsonResponse;

class EscalaController
{
    public function index(ListarEscalas $listar): JsonResponse
    {
        return response()->json($listar->ejecutar());
    }
}
