<?php

namespace App\Modules\GestionPersonal\Presentation\Controllers;

use App\Modules\GestionPersonal\Application\Ports\ContextoDeTenantPort;
use App\Modules\GestionPersonal\Application\UseCases\ConsultarOffboarding;
use App\Modules\GestionPersonal\Application\UseCases\GestionarEntrevista;
use App\Modules\GestionPersonal\Presentation\Requests\GuardarEntrevistaRequest;
use App\Modules\GestionPersonal\Presentation\Requests\ResolverSalidaRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Desvinculación de la propia ficha del Colaborador: solo autenticación; los casos de uso exigen la asociación
 * cuenta–ficha y devuelven únicamente lo comunicable. No expone respaldos, historial ni incidencias.
 */
class MiDesvinculacionController extends OffboardingBaseController
{
    public function __construct(
        ContextoDeTenantPort $contextos,
        private readonly ConsultarOffboarding $consultas,
        private readonly GestionarEntrevista $entrevista,
    ) {
        parent::__construct($contextos);
    }

    public function show(Request $request): JsonResponse
    {
        return $this->responder(fn () => response()->json(['data' => $this->consultas->miDesvinculacion($this->contexto($request), $this->actor($request))]));
    }

    public function entrevista(Request $request): JsonResponse
    {
        return $this->responder(fn () => response()->json(['data' => $this->consultas->miEntrevista($this->contexto($request), $this->actor($request))]));
    }

    public function guardar(GuardarEntrevistaRequest $request): JsonResponse
    {
        return $this->responder(function () use ($request) {
            $this->entrevista->guardar($this->contexto($request), $this->actor($request), $request->version(), $request->valoraciones(), $request->categorias(), $request->abiertas(), $request->clave());

            return response()->json(['data' => $this->consultas->miEntrevista($this->contexto($request), $this->actor($request))]);
        });
    }

    public function confirmar(ResolverSalidaRequest $request): JsonResponse
    {
        return $this->responder(function () use ($request) {
            $this->entrevista->confirmar($this->contexto($request), $this->actor($request), $request->version(), $request->clave());

            return response()->json(['data' => $this->consultas->miEntrevista($this->contexto($request), $this->actor($request))]);
        });
    }

    public function declinar(ResolverSalidaRequest $request): JsonResponse
    {
        return $this->responder(function () use ($request) {
            $this->entrevista->declinar($this->contexto($request), $this->actor($request), $request->version(), $request->clave());

            return response()->json(['data' => $this->consultas->miEntrevista($this->contexto($request), $this->actor($request))]);
        });
    }
}
