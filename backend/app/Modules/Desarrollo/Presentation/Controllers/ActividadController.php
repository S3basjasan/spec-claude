<?php

namespace App\Modules\Desarrollo\Presentation\Controllers;

use App\Modules\Desarrollo\Application\UseCases\ActualizarActividad;
use App\Modules\Desarrollo\Application\UseCases\CrearActividad;
use App\Modules\Desarrollo\Application\UseCases\ListarAccionesDisponibles;
use App\Modules\Desarrollo\Application\UseCases\ListarActividades;
use App\Modules\Desarrollo\Application\UseCases\ListarCalificaciones;
use App\Modules\Desarrollo\Application\UseCases\ListarDepartamentosParaActividad;
use App\Modules\Desarrollo\Application\UseCases\ListarHistorialDeActividad;
use App\Modules\Desarrollo\Application\UseCases\ObtenerActividad;
use App\Modules\Desarrollo\Application\UseCases\ObtenerOpcionesDeActividades;
use App\Modules\Desarrollo\Presentation\Requests\AccionesDisponiblesRequest;
use App\Modules\Desarrollo\Presentation\Requests\GuardarActividadRequest;
use App\Modules\Desarrollo\Presentation\Requests\HistorialActividadRequest;
use App\Modules\Desarrollo\Presentation\Requests\ListarActividadesRequest;
use App\Modules\Desarrollo\Presentation\Requests\ListarCalificacionesRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActividadController
{
    use RespondeErroresDeActividad;

    public function accionesDisponibles(AccionesDisponiblesRequest $request, ListarAccionesDisponibles $listar): JsonResponse
    {
        return response()->json($listar->ejecutar($this->tenant($request), $request->planId(), $request->busqueda(), $request->pagina(), $request->porPagina()));
    }

    public function departamentos(ListarDepartamentosParaActividad $listar): JsonResponse
    {
        return response()->json(['data' => $listar->ejecutar()]);
    }

    public function opciones(Request $request, ObtenerOpcionesDeActividades $opciones): JsonResponse
    {
        return response()->json(['data' => $opciones->ejecutar($this->tenant($request))]);
    }

    public function index(ListarActividadesRequest $request, ListarActividades $listar): JsonResponse
    {
        return response()->json($listar->ejecutar($this->tenant($request), $this->usuario($request), $request->filtro()));
    }

    public function store(GuardarActividadRequest $request, CrearActividad $crear): JsonResponse
    {
        return $this->responder(function () use ($request, $crear) {
            $actividad = $crear->ejecutar($this->tenant($request), $this->usuario($request), $request->accionId(), $request->lugarEnlace(), $request->departamentoId());

            return response()->json(['data' => $actividad], 201)->header('Location', url('/api/actividades/'.$actividad['id']));
        });
    }

    public function show(int $id, Request $request, ObtenerActividad $obtener): JsonResponse
    {
        return $this->responder(fn () => response()->json(['data' => $obtener->ejecutar($this->tenant($request), $this->usuario($request), $id)]));
    }

    public function update(int $id, GuardarActividadRequest $request, ActualizarActividad $actualizar): JsonResponse
    {
        return $this->responder(fn () => response()->json([
            'data' => $actualizar->ejecutar($this->tenant($request), $this->usuario($request), $id, $request->lugarEnlace(), $request->departamentoId()),
        ]));
    }

    public function historial(int $id, HistorialActividadRequest $request, ListarHistorialDeActividad $listar): JsonResponse
    {
        return $this->responder(fn () => response()->json($listar->ejecutar($this->tenant($request), $id, $request->pagina(), $request->porPagina())));
    }

    public function calificaciones(ListarCalificacionesRequest $request, ListarCalificaciones $listar): JsonResponse
    {
        return response()->json($listar->ejecutar($this->tenant($request), $request->filtro()));
    }
}
