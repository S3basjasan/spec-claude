<?php

namespace App\Modules\Desarrollo\Presentation\Controllers;

use App\Modules\Desarrollo\Application\UseCases\AceptarPdi;
use App\Modules\Desarrollo\Application\UseCases\ActualizarPdi;
use App\Modules\Desarrollo\Application\UseCases\CambiarResponsableDePdi;
use App\Modules\Desarrollo\Application\UseCases\CerrarPdi;
use App\Modules\Desarrollo\Application\UseCases\ConfirmarAcuerdoDePdi;
use App\Modules\Desarrollo\Application\UseCases\CrearPdi;
use App\Modules\Desarrollo\Application\UseCases\DevolverPdiABorrador;
use App\Modules\Desarrollo\Application\UseCases\DevolverPdiDelColaborador;
use App\Modules\Desarrollo\Application\UseCases\ListarColaboradoresSinPlan;
use App\Modules\Desarrollo\Application\UseCases\ListarHistorialDePdi;
use App\Modules\Desarrollo\Application\UseCases\ListarPdi;
use App\Modules\Desarrollo\Application\UseCases\ObtenerGanttDePdi;
use App\Modules\Desarrollo\Application\UseCases\ObtenerOpcionesDePdi;
use App\Modules\Desarrollo\Application\UseCases\ObtenerPdi;
use App\Modules\Desarrollo\Application\UseCases\ObtenerPermisosDePdi;
use App\Modules\Desarrollo\Application\UseCases\PrecargarPdi;
use App\Modules\Desarrollo\Presentation\Requests\ActualizarPdiRequest;
use App\Modules\Desarrollo\Presentation\Requests\CerrarPdiRequest;
use App\Modules\Desarrollo\Presentation\Requests\ComentarioDePdiRequest;
use App\Modules\Desarrollo\Presentation\Requests\CrearPdiRequest;
use App\Modules\Desarrollo\Presentation\Requests\GanttPdiRequest;
use App\Modules\Desarrollo\Presentation\Requests\HistorialPdiRequest;
use App\Modules\Desarrollo\Presentation\Requests\ListarPdiRequest;
use App\Modules\Desarrollo\Presentation\Requests\MotivoDePdiRequest;
use App\Modules\Desarrollo\Presentation\Requests\OpcionesPdiRequest;
use App\Modules\Desarrollo\Presentation\Requests\PrecargaPdiRequest;
use App\Modules\Desarrollo\Presentation\Requests\ResponsableDePdiRequest;
use App\Modules\Desarrollo\Presentation\Requests\SinPlanPdiRequest;
use App\Modules\Desarrollo\Presentation\Requests\VersionDePdiRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PdiController
{
    use RespondeErroresDePdi;

    public function permisos(Request $request, ObtenerPermisosDePdi $permisos): JsonResponse
    {
        return response()->json(['data' => $permisos->ejecutar($this->esAdministrador($request))]);
    }

    public function opciones(OpcionesPdiRequest $request, ObtenerOpcionesDePdi $opciones): JsonResponse
    {
        return $this->responder(fn () => response()->json(['data' => $opciones->ejecutar(
            $this->tenant($request), $this->usuario($request), $request->texto(), $request->colaboradorId(),
        )]));
    }

    public function precarga(PrecargaPdiRequest $request, PrecargarPdi $precargar): JsonResponse
    {
        return $this->responder(fn () => response()->json(['data' => $precargar->ejecutar(
            $this->tenant($request), $request->colaboradorId(), $request->tipo(), $request->puestoObjetivoId(),
        )]));
    }

    public function store(CrearPdiRequest $request, CrearPdi $crear): JsonResponse
    {
        return $this->responder(function () use ($request, $crear) {
            $pdi = $crear->ejecutar($this->tenant($request), $this->usuario($request), $request->datos());

            return response()->json(['data' => $pdi], 201)->header('Location', url('/api/pdi/'.$pdi['id']));
        });
    }

    public function index(ListarPdiRequest $request, ListarPdi $listar): JsonResponse
    {
        return $this->responder(fn () => response()->json($listar->ejecutar(
            $this->tenant($request), $this->usuario($request), $request->filtros(), $request->pagina(), $request->porPagina(),
        )));
    }

    public function gantt(GanttPdiRequest $request, ObtenerGanttDePdi $gantt): JsonResponse
    {
        return $this->responder(fn () => response()->json($gantt->ejecutar(
            $this->tenant($request), $this->usuario($request), $request->filtros(), $request->pagina(), $request->porPagina(),
        )));
    }

    public function sinPlan(SinPlanPdiRequest $request, ListarColaboradoresSinPlan $sinPlan): JsonResponse
    {
        return $this->responder(fn () => response()->json($sinPlan->ejecutar(
            $this->tenant($request), $this->usuario($request), $request->departamentoId(), $request->pagina(), $request->porPagina(),
        )));
    }

    public function historial(int $id, HistorialPdiRequest $request, ListarHistorialDePdi $historial): JsonResponse
    {
        return $this->responder(fn () => response()->json($historial->ejecutar(
            $this->tenant($request), $this->usuario($request), $id, $this->esAdministrador($request), $request->pagina(), $request->porPagina(),
        )));
    }

    public function show(int $id, Request $request, ObtenerPdi $obtener): JsonResponse
    {
        return $this->responder(fn () => response()->json(['data' => $obtener->ejecutar(
            $this->tenant($request), $this->usuario($request), $id, $this->esAdministrador($request),
        )]));
    }

    public function update(int $id, ActualizarPdiRequest $request, ActualizarPdi $actualizar): JsonResponse
    {
        return $this->responder(fn () => response()->json(['data' => $actualizar->ejecutar(
            $this->tenant($request), $this->usuario($request), $id, $request->datos(),
        )]));
    }

    public function acuerdo(int $id, VersionDePdiRequest $request, ConfirmarAcuerdoDePdi $confirmar): JsonResponse
    {
        return $this->responder(fn () => response()->json(['data' => $confirmar->ejecutar(
            $this->tenant($request), $this->usuario($request), $id, $request->version(),
        )]));
    }

    public function aceptar(int $id, VersionDePdiRequest $request, AceptarPdi $aceptar): JsonResponse
    {
        return $this->responder(fn () => response()->json(['data' => $aceptar->ejecutar(
            $this->tenant($request), $this->usuario($request), $id, $request->version(),
        )]));
    }

    public function devolverColaborador(int $id, ComentarioDePdiRequest $request, DevolverPdiDelColaborador $devolver): JsonResponse
    {
        return $this->responder(fn () => response()->json(['data' => $devolver->ejecutar(
            $this->tenant($request), $this->usuario($request), $id, $request->comentario(), $request->version(),
        )]));
    }

    public function devolver(int $id, MotivoDePdiRequest $request, DevolverPdiABorrador $devolver): JsonResponse
    {
        return $this->responder(fn () => response()->json(['data' => $devolver->ejecutar(
            $this->tenant($request), $this->usuario($request), $id, $request->motivo(), $request->version(),
        )]));
    }

    public function cerrar(int $id, CerrarPdiRequest $request, CerrarPdi $cerrar): JsonResponse
    {
        return $this->responder(fn () => response()->json(['data' => $cerrar->ejecutar(
            $this->tenant($request), $this->usuario($request), $id, $request->resultados(), $request->conclusion(), $request->version(),
        )]));
    }

    public function responsable(int $id, ResponsableDePdiRequest $request, CambiarResponsableDePdi $cambiar): JsonResponse
    {
        return $this->responder(fn () => response()->json(['data' => $cambiar->ejecutar(
            $this->tenant($request), $this->usuario($request), $id, $request->responsableId(), $request->version(),
        )]));
    }
}
