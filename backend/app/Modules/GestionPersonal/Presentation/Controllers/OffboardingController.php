<?php

namespace App\Modules\GestionPersonal\Presentation\Controllers;

use App\Modules\GestionPersonal\Application\DTO\ResultadoDeSalida;
use App\Modules\GestionPersonal\Application\Ports\ContextoDeTenantPort;
use App\Modules\GestionPersonal\Application\UseCases\AplicarSalida;
use App\Modules\GestionPersonal\Application\UseCases\CancelarSalida;
use App\Modules\GestionPersonal\Application\UseCases\CompletarTarea;
use App\Modules\GestionPersonal\Application\UseCases\ConfirmarCierre;
use App\Modules\GestionPersonal\Application\UseCases\ConsultarEntrevistas;
use App\Modules\GestionPersonal\Application\UseCases\ConsultarOffboarding;
use App\Modules\GestionPersonal\Application\UseCases\FormalizarSalida;
use App\Modules\GestionPersonal\Application\UseCases\GestionarEntrevista;
use App\Modules\GestionPersonal\Application\UseCases\ListarDocumentosReferenciables;
use App\Modules\GestionPersonal\Application\UseCases\ObtenerContextoDeSalida;
use App\Modules\GestionPersonal\Presentation\Requests\ContextoDeColaboradorRequest;
use App\Modules\GestionPersonal\Presentation\Requests\FormalizarSalidaRequest;
use App\Modules\GestionPersonal\Presentation\Requests\ListarSalidasRequest;
use App\Modules\GestionPersonal\Presentation\Requests\ResolverSalidaRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/** Salidas del tenant para el Administrador de Talento Humano. Sin PUT, PATCH ni DELETE. */
class OffboardingController extends OffboardingBaseController
{
    public function __construct(ContextoDeTenantPort $contextos, private readonly ConsultarOffboarding $consultas)
    {
        parent::__construct($contextos);
    }

    public function index(ListarSalidasRequest $request): JsonResponse
    {
        return $this->responder(function () use ($request) {
            $resultado = $this->consultas->listar($this->contexto($request), $request->filtros(), $request->pagina(), $request->porPagina());

            $paginador = (new LengthAwarePaginator($resultado['items'], $resultado['total'], $resultado['por_pagina'], $resultado['pagina'], ['path' => $request->url()]))
                ->appends($request->query());

            return response()->json([
                'data' => $resultado['items'],
                'links' => [
                    'first' => $paginador->url(1),
                    'last' => $paginador->url($paginador->lastPage()),
                    'prev' => $paginador->previousPageUrl(),
                    'next' => $paginador->nextPageUrl(),
                ],
                'meta' => [
                    'current_page' => $paginador->currentPage(),
                    'per_page' => $paginador->perPage(),
                    'total' => $paginador->total(),
                    'last_page' => $paginador->lastPage(),
                    'from' => $paginador->firstItem(),
                    'to' => $paginador->lastItem(),
                ],
            ]);
        });
    }

    public function resumen(Request $request): JsonResponse
    {
        return $this->responder(fn () => response()->json(['data' => $this->consultas->resumen($this->contexto($request))]));
    }

    public function contextoDeSalida(ContextoDeColaboradorRequest $request, ObtenerContextoDeSalida $contexto): JsonResponse
    {
        return $this->responder(fn () => response()->json(['data' => $contexto->ejecutar($this->contexto($request), $this->actor($request), $request->colaboradorId())]));
    }

    public function documentos(ContextoDeColaboradorRequest $request, ListarDocumentosReferenciables $documentos): JsonResponse
    {
        return $this->responder(fn () => response()->json(['data' => $documentos->comoAdministrador($this->contexto($request), $this->actor($request), $request->colaboradorId())]));
    }

    public function agregados(Request $request, ConsultarEntrevistas $entrevistas): JsonResponse
    {
        return $this->responder(fn () => response()->json(['data' => $entrevistas->agregados($this->contexto($request))]));
    }

    public function store(FormalizarSalidaRequest $request, FormalizarSalida $formalizar): JsonResponse
    {
        return $this->responder(function () use ($request, $formalizar) {
            $contexto = $this->contexto($request);
            $resultado = $formalizar->ejecutar($contexto, $this->actor($request), $request->colaboradorId(), $request->input('motivo'), $request->input('fecha_efectiva'), $request->documentoIds(), $request->clave());

            return $this->detalle($request, $resultado, $resultado->nueva ? 201 : 200);
        });
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return $this->responder(fn () => response()->json(['data' => $this->consultas->detalle($this->contexto($request), $id)]));
    }

    public function cancelacion(ResolverSalidaRequest $request, int $id, CancelarSalida $cancelar): JsonResponse
    {
        return $this->responder(fn () => $this->detalle($request, $cancelar->ejecutar($this->contexto($request), $this->actor($request), $id, $request->version(), $request->input('razon'), $request->clave())));
    }

    public function completarTarea(ResolverSalidaRequest $request, int $id, int $tarea, CompletarTarea $completar): JsonResponse
    {
        return $this->responder(fn () => $this->detalle($request, $completar->ejecutar($this->contexto($request), $this->actor($request), $id, $tarea, $request->version(), $request->documentoIds(), $request->clave())));
    }

    public function aplicacion(ResolverSalidaRequest $request, int $id, AplicarSalida $aplicar): JsonResponse
    {
        return $this->responder(fn () => $this->detalle($request, $aplicar->comoAdministrador($this->contexto($request), $this->actor($request), $id, $request->version(), $request->clave())));
    }

    public function cierre(ResolverSalidaRequest $request, int $id, ConfirmarCierre $cerrar): JsonResponse
    {
        return $this->responder(fn () => $this->detalle($request, $cerrar->ejecutar($this->contexto($request), $this->actor($request), $id, $request->version(), $request->clave())));
    }

    public function entrevistaNoRealizada(ResolverSalidaRequest $request, int $id, GestionarEntrevista $entrevista): JsonResponse
    {
        // `version` es la de la entrevista, tal como la muestra el detalle del expediente.
        return $this->responder(fn () => $this->detalle($request, $entrevista->noRealizada($this->contexto($request), $this->actor($request), $id, $request->version(), $request->input('motivo'), $request->clave())));
    }

    public function respuestas(Request $request, int $id, ConsultarEntrevistas $entrevistas): JsonResponse
    {
        return $this->responder(fn () => response()->json(['data' => $entrevistas->respuestas($this->contexto($request), $this->actor($request), $id)]));
    }

    public function estructura(Request $request, int $id, ConsultarEntrevistas $entrevistas): JsonResponse
    {
        return $this->responder(fn () => response()->json(['data' => $entrevistas->estructura($this->contexto($request), $this->actor($request), $id)]));
    }

    private function detalle(Request $request, ResultadoDeSalida $resultado, int $estado = 200): JsonResponse
    {
        return response()->json(['data' => $this->consultas->detalle($this->contexto($request), $resultado->expediente->id)], $estado);
    }
}
