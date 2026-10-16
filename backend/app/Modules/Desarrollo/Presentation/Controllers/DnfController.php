<?php

namespace App\Modules\Desarrollo\Presentation\Controllers;

use App\Modules\Desarrollo\Application\UseCases\GenerarDnf;
use App\Modules\Desarrollo\Application\UseCases\GenerarPlanDesdeDnf;
use App\Modules\Desarrollo\Application\UseCases\ListarDnf;
use App\Modules\Desarrollo\Application\UseCases\ObtenerDnf;
use App\Modules\Desarrollo\Application\UseCases\ObtenerMiDiagnostico;
use App\Modules\Desarrollo\Application\UseCases\ObtenerOpcionesDnf;
use App\Modules\Desarrollo\Domain\Exceptions\ColaboradorNoEvaluable;
use App\Modules\Desarrollo\Domain\Exceptions\DepartamentoNoEncontrado;
use App\Modules\Desarrollo\Domain\Exceptions\DnfNoEncontrada;
use App\Modules\Desarrollo\Domain\Exceptions\DnfSinBrechas;
use App\Modules\Desarrollo\Domain\Exceptions\PlanNoDisponible;
use App\Modules\Desarrollo\Domain\Exceptions\PlanYaGenerado;
use App\Modules\Desarrollo\Domain\Exceptions\SinColaboradoresEvaluables;
use App\Modules\Desarrollo\Presentation\Requests\ConsultarDnfRequest;
use App\Modules\Desarrollo\Presentation\Requests\GenerarDnfRequest;
use App\Modules\Desarrollo\Presentation\Requests\ListarDnfRequest;
use App\Modules\Desarrollo\Presentation\Resources\DnfResource;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DnfController
{
    public function opciones(Request $request, ObtenerOpcionesDnf $opciones): JsonResponse
    {
        return response()->json($opciones->ejecutar($this->tenant($request))->toArray());
    }

    public function store(GenerarDnfRequest $request, GenerarDnf $generar): JsonResponse
    {
        return $this->responder(function () use ($request, $generar) {
            $resultado = $generar->ejecutar(
                $this->tenant($request),
                (int) $request->user()->getAuthIdentifier(),
                $request->alcance(),
                $request->colaboradorId(),
                $request->departamentoId(),
            );

            return (new DnfResource($resultado['dnf']))
                ->additional(['avisos' => $resultado['avisos']->toArray()])
                ->response()
                ->setStatusCode(201)
                ->header('Location', url('/api/dnf/'.$resultado['dnf']->id));
        });
    }

    public function index(ListarDnfRequest $request, ListarDnf $listar): JsonResponse
    {
        return response()->json($listar->ejecutar($this->tenant($request), $request->filtro())->toArray());
    }

    public function show(int $id, ConsultarDnfRequest $request, ObtenerDnf $obtener): JsonResponse
    {
        return $this->responder(function () use ($id, $request, $obtener) {
            $resultado = $obtener->ejecutar($this->tenant($request), $id, $request->colaboradorId());

            return (new DnfResource($resultado['dnf']))
                ->additional(['avisos' => $resultado['avisos']->toArray()])
                ->response();
        });
    }

    public function plan(int $id, Request $request, GenerarPlanDesdeDnf $generarPlan): JsonResponse
    {
        return $this->responder(fn () => response()->json([
            'data' => $generarPlan->ejecutar($this->tenant($request), (int) $request->user()->getAuthIdentifier(), $id),
        ], 201));
    }

    /** Diagnóstico propio: el usuario sale del token, nunca de la petición. */
    public function miDiagnostico(Request $request, ObtenerMiDiagnostico $miDiagnostico): JsonResponse
    {
        $diagnostico = $miDiagnostico->ejecutar((int) $request->user()->getAuthIdentifier());

        return response()->json(['data' => $diagnostico?->toArray()]);
    }

    private function tenant(Request $request): int
    {
        return (int) $request->user()->tenant_id;
    }

    /**
     * Traduce las excepciones de dominio a respuestas HTTP sin revelar detalles internos.
     *
     * @param  Closure(): JsonResponse  $accion
     */
    private function responder(Closure $accion): JsonResponse
    {
        try {
            return $accion();
        } catch (ColaboradorNoEvaluable $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['colaborador_id' => [$e->getMessage()]],
            ], 422);
        } catch (DepartamentoNoEncontrado $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['departamento_id' => [$e->getMessage()]],
            ], 422);
        } catch (DnfNoEncontrada $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (DnfSinBrechas|PlanYaGenerado $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (PlanNoDisponible $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        } catch (SinColaboradoresEvaluables $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
