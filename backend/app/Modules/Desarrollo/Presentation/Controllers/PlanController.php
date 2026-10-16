<?php

namespace App\Modules\Desarrollo\Presentation\Controllers;

use App\Modules\Desarrollo\Application\UseCases\ActualizarPlan;
use App\Modules\Desarrollo\Application\UseCases\BuscarColaboradoresParaPlan;
use App\Modules\Desarrollo\Application\UseCases\CambiarEstadoDePlan;
use App\Modules\Desarrollo\Application\UseCases\CrearPlan;
use App\Modules\Desarrollo\Application\UseCases\ListarCursosParaPlan;
use App\Modules\Desarrollo\Application\UseCases\ListarHistorialDePlan;
use App\Modules\Desarrollo\Application\UseCases\ListarPlanes;
use App\Modules\Desarrollo\Application\UseCases\ObtenerMiPlan;
use App\Modules\Desarrollo\Application\UseCases\ObtenerOpcionesDePlan;
use App\Modules\Desarrollo\Application\UseCases\ObtenerPlan;
use App\Modules\Desarrollo\Application\UseCases\ReasignarAprobador;
use App\Modules\Desarrollo\Presentation\Requests\CambiarEstadoRequest;
use App\Modules\Desarrollo\Presentation\Requests\GuardarPlanRequest;
use App\Modules\Desarrollo\Presentation\Requests\ListarHistorialPlanRequest;
use App\Modules\Desarrollo\Presentation\Requests\ListarPlanesRequest;
use App\Modules\Desarrollo\Presentation\Requests\ReasignarAprobadorRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanController
{
    use RespondeErroresDePlan;

    public function index(ListarPlanesRequest $request, ListarPlanes $listar): JsonResponse
    {
        return response()->json($listar->ejecutar($this->tenant($request), $this->usuario($request), $request->filtro()));
    }

    public function store(GuardarPlanRequest $request, CrearPlan $crear): JsonResponse
    {
        return $this->responder(function () use ($request, $crear) {
            $plan = $crear->ejecutar($this->tenant($request), $this->usuario($request), $request->datos());

            return response()->json(['data' => $plan], 201)->header('Location', url('/api/planes/'.$plan['id']));
        });
    }

    public function show(int $id, Request $request, ObtenerPlan $obtener): JsonResponse
    {
        return $this->responder(fn () => response()->json(['data' => $obtener->ejecutar($this->tenant($request), $this->usuario($request), $id)]));
    }

    public function update(int $id, GuardarPlanRequest $request, ActualizarPlan $actualizar): JsonResponse
    {
        return $this->responder(fn () => response()->json([
            'data' => $actualizar->ejecutar($this->tenant($request), $this->usuario($request), $id, $request->datos()),
        ]));
    }

    /** Plan propio del colaborador: el usuario sale del token, nunca de la petición. */
    public function miPlan(Request $request, ObtenerMiPlan $miPlan): JsonResponse
    {
        return response()->json(['data' => $miPlan->ejecutar($this->usuario($request))]);
    }

    public function historial(int $id, ListarHistorialPlanRequest $request, ListarHistorialDePlan $historial): JsonResponse
    {
        return $this->responder(fn () => response()->json($historial->ejecutar($this->tenant($request), $id, $request->pagina(), $request->porPagina())));
    }

    public function estado(int $id, CambiarEstadoRequest $request, CambiarEstadoDePlan $cambiar): JsonResponse
    {
        return $this->responder(fn () => response()->json([
            'data' => $cambiar->ejecutar($this->tenant($request), $this->usuario($request), $id, $request->destino(), $request->motivo()),
        ]));
    }

    public function aprobador(int $id, ReasignarAprobadorRequest $request, ReasignarAprobador $reasignar): JsonResponse
    {
        return $this->responder(fn () => response()->json([
            'data' => $reasignar->ejecutar($this->tenant($request), $this->usuario($request), $id, $request->aprobadorId()),
        ]));
    }

    public function opciones(Request $request, ObtenerOpcionesDePlan $opciones): JsonResponse
    {
        return response()->json($opciones->ejecutar($this->tenant($request)));
    }

    public function colaboradores(Request $request, BuscarColaboradoresParaPlan $buscar): JsonResponse
    {
        $datos = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        return response()->json($buscar->ejecutar(
            $this->tenant($request),
            $this->usuario($request),
            $datos['q'] ?? null,
            (int) ($datos['page'] ?? 1),
            (int) ($datos['per_page'] ?? 20),
        ));
    }

    public function cursos(Request $request, ListarCursosParaPlan $cursos): JsonResponse
    {
        $datos = $request->validate(['q' => ['nullable', 'string', 'max:120']]);

        return response()->json($cursos->ejecutar($datos['q'] ?? null));
    }
}
