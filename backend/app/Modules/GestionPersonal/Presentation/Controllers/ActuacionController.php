<?php

namespace App\Modules\GestionPersonal\Presentation\Controllers;

use App\Modules\GestionPersonal\Application\DTO\ResultadoDeActuacion;
use App\Modules\GestionPersonal\Application\Ports\ContextoDeTenantPort;
use App\Modules\GestionPersonal\Application\UseCases\AplicarMovimiento;
use App\Modules\GestionPersonal\Application\UseCases\AprobarActuacion;
use App\Modules\GestionPersonal\Application\UseCases\InformarAusencia;
use App\Modules\GestionPersonal\Application\UseCases\ListarActuaciones;
use App\Modules\GestionPersonal\Application\UseCases\ListarDocumentosReferenciables;
use App\Modules\GestionPersonal\Application\UseCases\ObtenerActuacion;
use App\Modules\GestionPersonal\Application\UseCases\ObtenerContextoDeMovimiento;
use App\Modules\GestionPersonal\Application\UseCases\PasarARevision;
use App\Modules\GestionPersonal\Application\UseCases\PresentarNovedad;
use App\Modules\GestionPersonal\Application\UseCases\ProponerMovimiento;
use App\Modules\GestionPersonal\Application\UseCases\RechazarActuacion;
use App\Modules\GestionPersonal\Application\UseCases\ResumirActuaciones;
use App\Modules\GestionPersonal\Application\UseCases\RetirarNovedad;
use App\Modules\GestionPersonal\Application\UseCases\RevisarAusencia;
use App\Modules\GestionPersonal\Presentation\Requests\ContextoDeColaboradorRequest;
use App\Modules\GestionPersonal\Presentation\Requests\ListarActuacionesRequest;
use App\Modules\GestionPersonal\Presentation\Requests\PresentarActuacionRequest;
use App\Modules\GestionPersonal\Presentation\Requests\ResolverActuacionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/** Movimientos y novedades del tenant para el Administrador de Talento Humano. Sin PUT, PATCH ni DELETE. */
class ActuacionController extends ActuacionBaseController
{
    public function __construct(
        ContextoDeTenantPort $contextos,
        private readonly ObtenerActuacion $obtener,
    ) {
        parent::__construct($contextos);
    }

    public function index(ListarActuacionesRequest $request, ListarActuaciones $listar): JsonResponse
    {
        return $this->responder(function () use ($request, $listar) {
            $resultado = $listar->ejecutar($this->contexto($request), $request->filtros(), $request->pagina(), $request->porPagina());

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

    public function resumen(Request $request, ResumirActuaciones $resumir): JsonResponse
    {
        return $this->responder(fn () => response()->json(['data' => $resumir->ejecutar($this->contexto($request))]));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return $this->responder(fn () => response()->json(['data' => $this->obtener->comoAdministrador($this->contexto($request), $id)]));
    }

    public function contextoDeMovimiento(ContextoDeColaboradorRequest $request, ObtenerContextoDeMovimiento $contexto): JsonResponse
    {
        return $this->responder(fn () => response()->json(['data' => $contexto->ejecutar(
            $this->contexto($request),
            $this->actor($request),
            $request->colaboradorId(),
            $request->texto(),
            $request->pagina(),
            $request->porPagina(),
        )]));
    }

    public function documentos(ContextoDeColaboradorRequest $request, ListarDocumentosReferenciables $documentos): JsonResponse
    {
        return $this->responder(fn () => response()->json(['data' => $documentos->comoAdministrador(
            $this->contexto($request), $this->actor($request), $request->colaboradorId(),
        )]));
    }

    public function novedad(PresentarActuacionRequest $request, PresentarNovedad $presentar): JsonResponse
    {
        return $this->responder(fn () => $this->creada($request, $presentar->comoAdministrador(
            $this->contexto($request), $this->actor($request), $request->colaboradorId(), $request->input('tipo'), $request->datos(), $request->clave(),
        )));
    }

    public function ausencia(PresentarActuacionRequest $request, InformarAusencia $informar): JsonResponse
    {
        return $this->responder(fn () => $this->creada($request, $informar->ejecutar(
            $this->contexto($request), $this->actor($request), $request->colaboradorId(), $request->datos(), $request->clave(),
        )));
    }

    public function movimiento(PresentarActuacionRequest $request, ProponerMovimiento $proponer): JsonResponse
    {
        return $this->responder(fn () => $this->creada($request, $proponer->ejecutar(
            $this->contexto($request), $this->actor($request), $request->colaboradorId(), $request->input('tipo'), $request->datos(), $request->clave(),
        )));
    }

    public function revision(ResolverActuacionRequest $request, int $id, PasarARevision $revisar): JsonResponse
    {
        return $this->responder(fn () => $this->resuelta($request, $revisar->ejecutar(
            $this->contexto($request), $this->actor($request), $id, $request->version(), $request->clave(),
        )));
    }

    public function aprobacion(ResolverActuacionRequest $request, int $id, AprobarActuacion $aprobar): JsonResponse
    {
        return $this->responder(fn () => $this->resuelta($request, $aprobar->ejecutar(
            $this->contexto($request), $this->actor($request), $id, $request->version(),
            $request->input('justificacion'), $request->boolean('reconoce_cruce'), $request->clave(),
        )));
    }

    public function rechazo(ResolverActuacionRequest $request, int $id, RechazarActuacion $rechazar): JsonResponse
    {
        return $this->responder(fn () => $this->resuelta($request, $rechazar->ejecutar(
            $this->contexto($request), $this->actor($request), $id, $request->version(), $request->input('justificacion'), $request->clave(),
        )));
    }

    public function retiro(ResolverActuacionRequest $request, int $id, RetirarNovedad $retirar): JsonResponse
    {
        return $this->responder(fn () => $this->resuelta($request, $retirar->comoAdministrador(
            $this->contexto($request), $this->actor($request), $id, $request->version(), $request->clave(),
        )));
    }

    public function revisionDeAusencia(ResolverActuacionRequest $request, int $id, RevisarAusencia $revisar): JsonResponse
    {
        return $this->responder(fn () => $this->resuelta($request, $revisar->ejecutar(
            $this->contexto($request), $this->actor($request), $id, $request->version(),
            $request->input('resultado'), $request->input('observacion'), $request->clave(),
        )));
    }

    public function aplicacion(ResolverActuacionRequest $request, int $id, AplicarMovimiento $aplicar): JsonResponse
    {
        return $this->responder(fn () => $this->resuelta($request, $aplicar->comoAdministrador(
            $this->contexto($request), $this->actor($request), $id, $request->version(), $request->clave(),
        )));
    }

    private function creada(Request $request, ResultadoDeActuacion $resultado): JsonResponse
    {
        return response()->json(['data' => $this->obtener->comoAdministrador($this->contexto($request), $resultado->actuacion->id)], $resultado->nueva ? 201 : 200);
    }

    private function resuelta(Request $request, ResultadoDeActuacion $resultado): JsonResponse
    {
        return response()->json(['data' => $this->obtener->comoAdministrador($this->contexto($request), $resultado->actuacion->id)]);
    }
}
