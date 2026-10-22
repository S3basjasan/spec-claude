<?php

namespace App\Modules\Desarrollo\Presentation\Controllers;

use App\Modules\Desarrollo\Application\UseCases\ActualizarParametrosDeAdecuacion;
use App\Modules\Desarrollo\Application\UseCases\CompararCandidatos;
use App\Modules\Desarrollo\Application\UseCases\GenerarAdecuacion;
use App\Modules\Desarrollo\Application\UseCases\ListarAdecuaciones;
use App\Modules\Desarrollo\Application\UseCases\ObtenerAdecuacion;
use App\Modules\Desarrollo\Application\UseCases\ObtenerMapaDeTalento;
use App\Modules\Desarrollo\Application\UseCases\ObtenerOpcionesDeAdecuacion;
use App\Modules\Desarrollo\Application\UseCases\ObtenerParametrosDeAdecuacion;
use App\Modules\Desarrollo\Application\UseCases\ObtenerPermisosDeAdecuacion;
use App\Modules\Desarrollo\Application\UseCases\PrevisualizarAdecuacion;
use App\Modules\Desarrollo\Presentation\Requests\ActualizarParametrosRequest;
use App\Modules\Desarrollo\Presentation\Requests\CompararCandidatosRequest;
use App\Modules\Desarrollo\Presentation\Requests\GenerarAdecuacionRequest;
use App\Modules\Desarrollo\Presentation\Requests\HistorialParametrosRequest;
use App\Modules\Desarrollo\Presentation\Requests\ListarAdecuacionesRequest;
use App\Modules\Desarrollo\Presentation\Requests\MapaDeTalentoRequest;
use App\Modules\Desarrollo\Presentation\Requests\OpcionesAdecuacionRequest;
use App\Modules\Desarrollo\Presentation\Requests\PrevisualizarAdecuacionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdecuacionesController
{
    use RespondeErroresDeAdecuacion;

    public function permisos(Request $request, ObtenerPermisosDeAdecuacion $permisos): JsonResponse
    {
        return response()->json(['data' => $permisos->ejecutar($this->esAdministrador($request))]);
    }

    public function opciones(OpcionesAdecuacionRequest $request, ObtenerOpcionesDeAdecuacion $opciones): JsonResponse
    {
        return $this->responder(fn () => response()->json(['data' => $opciones->ejecutar($this->tenant($request), $request->texto(), $request->colaboradorId())]));
    }

    public function previsualizar(PrevisualizarAdecuacionRequest $request, PrevisualizarAdecuacion $previsualizar): JsonResponse
    {
        return $this->responder(fn () => response()->json(['data' => $previsualizar->ejecutar(
            $this->tenant($request), $request->colaboradorId(), $request->inscripcionId(), $request->puestoObjetivoId(), $request->experienciaRequerida(),
        )]));
    }

    public function store(GenerarAdecuacionRequest $request, GenerarAdecuacion $generar): JsonResponse
    {
        return $this->responder(function () use ($request, $generar) {
            $adecuacion = $generar->ejecutar($this->tenant($request), $this->usuario($request), $request->datos());

            return response()->json(['data' => $adecuacion], 201)->header('Location', url('/api/adecuaciones/'.$adecuacion['id']));
        });
    }

    public function index(ListarAdecuacionesRequest $request, ListarAdecuaciones $listar): JsonResponse
    {
        return $this->responder(fn () => response()->json($listar->ejecutar($this->tenant($request), $request->filtros(), $request->pagina(), $request->porPagina())));
    }

    public function parametros(HistorialParametrosRequest $request, ObtenerParametrosDeAdecuacion $parametros): JsonResponse
    {
        return $this->responder(fn () => response()->json(['data' => $parametros->ejecutar($this->tenant($request), $request->pagina(), $request->porPagina())]));
    }

    public function actualizarParametros(ActualizarParametrosRequest $request, ActualizarParametrosDeAdecuacion $actualizar): JsonResponse
    {
        return $this->responder(fn () => response()->json(['data' => $actualizar->ejecutar($this->tenant($request), $this->usuario($request), $request->pesos(), $request->umbralMeses())]));
    }

    public function mapa(MapaDeTalentoRequest $request, ObtenerMapaDeTalento $mapa): JsonResponse
    {
        return $this->responder(fn () => response()->json($mapa->ejecutar($this->tenant($request), $request->filtros(), $request->pagina(), $request->porPagina())));
    }

    public function comparar(CompararCandidatosRequest $request, CompararCandidatos $comparar): JsonResponse
    {
        return $this->responder(fn () => response()->json(['data' => $comparar->ejecutar($this->tenant($request), $request->puestoObjetivoId(), $request->colaboradorIds(), $request->pesos())]));
    }

    public function show(int $id, Request $request, ObtenerAdecuacion $obtener): JsonResponse
    {
        return $this->responder(fn () => response()->json(['data' => $obtener->ejecutar($this->tenant($request), $id)]));
    }
}
