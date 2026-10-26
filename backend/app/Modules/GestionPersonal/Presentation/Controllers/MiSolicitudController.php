<?php

namespace App\Modules\GestionPersonal\Presentation\Controllers;

use App\Modules\GestionPersonal\Application\Ports\ContextoDeTenantPort;
use App\Modules\GestionPersonal\Application\UseCases\ListarDocumentosReferenciables;
use App\Modules\GestionPersonal\Application\UseCases\ListarMisSolicitudes;
use App\Modules\GestionPersonal\Application\UseCases\ObtenerActuacion;
use App\Modules\GestionPersonal\Application\UseCases\PresentarNovedad;
use App\Modules\GestionPersonal\Application\UseCases\RetirarNovedad;
use App\Modules\GestionPersonal\Presentation\Requests\ListarActuacionesRequest;
use App\Modules\GestionPersonal\Presentation\Requests\PresentarActuacionRequest;
use App\Modules\GestionPersonal\Presentation\Requests\ResolverActuacionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Solicitudes de la propia ficha del Colaborador: solo autenticación; los casos de uso exigen la asociación cuenta–ficha
 * y devuelven únicamente lo comunicable. Una solicitud ajena responde igual que una inexistente.
 */
class MiSolicitudController extends ActuacionBaseController
{
    public function __construct(ContextoDeTenantPort $contextos, private readonly ObtenerActuacion $obtener)
    {
        parent::__construct($contextos);
    }

    public function index(ListarActuacionesRequest $request, ListarMisSolicitudes $listar): JsonResponse
    {
        return $this->responder(function () use ($request, $listar) {
            $filtros = $request->filtros();

            return response()->json(['data' => $listar->ejecutar($this->contexto($request), $this->actor($request), $filtros['tipo'], $filtros['estado'])]);
        });
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return $this->responder(fn () => response()->json(['data' => $this->obtener->comoColaborador($this->contexto($request), $this->actor($request), $id)]));
    }

    public function documentos(Request $request, ListarDocumentosReferenciables $documentos): JsonResponse
    {
        return $this->responder(fn () => response()->json(['data' => $documentos->comoColaborador($this->contexto($request), $this->actor($request))]));
    }

    public function novedad(PresentarActuacionRequest $request, PresentarNovedad $presentar): JsonResponse
    {
        return $this->responder(function () use ($request, $presentar) {
            $resultado = $presentar->comoColaborador($this->contexto($request), $this->actor($request), $request->input('tipo'), $request->datos(), $request->clave());

            return response()->json(
                ['data' => $this->obtener->comoColaborador($this->contexto($request), $this->actor($request), $resultado->actuacion->id)],
                $resultado->nueva ? 201 : 200,
            );
        });
    }

    public function retiro(ResolverActuacionRequest $request, int $id, RetirarNovedad $retirar): JsonResponse
    {
        return $this->responder(function () use ($request, $id, $retirar) {
            $resultado = $retirar->comoColaborador($this->contexto($request), $this->actor($request), $id, $request->version(), $request->clave());

            return response()->json(['data' => $this->obtener->comoColaborador($this->contexto($request), $this->actor($request), $resultado->actuacion->id)]);
        });
    }
}
