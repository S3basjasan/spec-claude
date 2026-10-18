<?php

namespace App\Modules\Estructura\Presentation\Controllers;

use App\Modules\Estructura\Application\UseCases\ObtenerOrganigrama;
use App\Modules\Estructura\Domain\Exceptions\OrganigramaInconsistente;
use App\Modules\Estructura\Presentation\Resources\OrganigramaResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrganigramaController
{
    public function show(Request $request, ObtenerOrganigrama $obtener): JsonResponse
    {
        try {
            return (new OrganigramaResource($obtener->ejecutar((int) $request->user()->getAuthIdentifier())))->response();
        } catch (OrganigramaInconsistente $e) {
            // Los ids afectados solo van al registro del servidor; el cliente recibe un mensaje genérico.
            Log::error('Las posiciones vigentes no forman un único árbol.', ['motivo' => $e->motivo, 'ids' => $e->ids]);

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
