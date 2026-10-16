<?php

namespace App\Modules\GestionPersonal\Presentation\Controllers;

use App\Modules\GestionPersonal\Application\Ports\ContextoDeTenantPort;
use App\Modules\GestionPersonal\Application\UseCases\ObtenerMiDesarrollo;
use App\Modules\GestionPersonal\Domain\Exceptions\CuentaSinFicha;
use App\Modules\GestionPersonal\Domain\Exceptions\SinPermisoDeFicha;
use App\Modules\GestionPersonal\Presentation\Resources\DesarrolloResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MiDesarrolloController
{
    public function __construct(private readonly ContextoDeTenantPort $contextos) {}

    public function show(Request $request, ObtenerMiDesarrollo $obtener): JsonResponse
    {
        $userId = (int) $request->user()->getAuthIdentifier();
        $contexto = $this->contextos->deCuenta($userId);

        if ($contexto === null) {
            return response()->json(['message' => (new CuentaSinFicha)->getMessage(), 'code' => 'CUENTA_SIN_FICHA'], 403);
        }

        try {
            return (new DesarrolloResource($obtener->ejecutar($contexto, $userId)))->response();
        } catch (CuentaSinFicha $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'CUENTA_SIN_FICHA'], 403);
        } catch (SinPermisoDeFicha $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'SIN_PERMISO_FICHA'], 403);
        }
    }
}
