<?php

namespace App\Modules\GestionPersonal\Presentation\Controllers;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\Ports\ContextoDeTenantPort;
use App\Modules\GestionPersonal\Application\UseCases\ObtenerOpcionesDeIngreso;
use App\Modules\GestionPersonal\Application\UseCases\RegistrarIngreso;
use App\Modules\GestionPersonal\Domain\Exceptions\ClaveDeOperacionRequerida;
use App\Modules\GestionPersonal\Domain\Exceptions\ClaveDeOperacionReutilizada;
use App\Modules\GestionPersonal\Domain\Exceptions\ColaboradorNoEncontrado;
use App\Modules\GestionPersonal\Domain\Exceptions\ContratoNoHabilitado;
use App\Modules\GestionPersonal\Domain\Exceptions\DatosInvalidos;
use App\Modules\GestionPersonal\Domain\Exceptions\IngresoNoVerificable;
use App\Modules\GestionPersonal\Domain\Exceptions\IngresosDeshabilitados;
use App\Modules\GestionPersonal\Domain\Exceptions\PosicionNoDisponible;
use App\Modules\GestionPersonal\Domain\Exceptions\VinculoSuperpuesto;
use App\Modules\GestionPersonal\Presentation\Requests\OpcionesDeIngresoRequest;
use App\Modules\GestionPersonal\Presentation\Requests\RegistrarIngresoRequest;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class IngresoController
{
    public function __construct(private readonly ContextoDeTenantPort $contextos) {}

    public function store(RegistrarIngresoRequest $request, int $colaboradorId, RegistrarIngreso $registrar): JsonResponse
    {
        return $this->responder(function () use ($request, $colaboradorId, $registrar) {
            $resultado = $registrar->ejecutar(
                $this->contexto($request),
                $this->actor($request),
                $colaboradorId,
                $request->header('Idempotency-Key'),
                $request->datos(),
            );

            return response()->json(['data' => $resultado->datos], $resultado->creado ? 201 : 200);
        });
    }

    public function opciones(OpcionesDeIngresoRequest $request, int $colaboradorId, ObtenerOpcionesDeIngreso $opciones): JsonResponse
    {
        return $this->responder(function () use ($request, $colaboradorId, $opciones) {
            $resultado = $opciones->ejecutar(
                $this->contexto($request),
                $colaboradorId,
                (string) $request->validated('fecha'),
                $request->validated('q'),
                (int) ($request->validated('page') ?? 1),
                (int) ($request->validated('per_page') ?? 20),
            );

            $paginador = (new LengthAwarePaginator(
                $resultado['posiciones'],
                $resultado['total'],
                $resultado['por_pagina'],
                $resultado['pagina'],
                ['path' => $request->url()],
            ))->appends($request->query());

            return response()->json([
                'data' => [
                    'tipos_contrato' => $resultado['tipos_contrato'],
                    'ingreso_programado' => $resultado['ingreso_programado'],
                    'conflicto_ficha' => $resultado['conflicto_ficha'],
                    'posiciones' => $resultado['posiciones'],
                ],
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

    private function actor(Request $request): int
    {
        return (int) $request->user()->getAuthIdentifier();
    }

    private function contexto(Request $request): ContextoDeTenant
    {
        return $this->contextos->deCuenta($this->actor($request)) ?? throw new ColaboradorNoEncontrado;
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
        } catch (ColaboradorNoEncontrado $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'FICHA_NO_ENCONTRADA'], 404);
        } catch (DatosInvalidos $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'VALIDACION', 'errors' => $e->errores], 422);
        } catch (ContratoNoHabilitado $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'CONTRATO_NO_HABILITADO',
                'errors' => ['tipo_contrato' => [$e->getMessage()]],
            ], 422);
        } catch (PosicionNoDisponible $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'POSICION_NO_DISPONIBLE',
                'errors' => ['posicion_id' => [$e->getMessage()]],
            ], 422);
        } catch (VinculoSuperpuesto $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'VINCULO_SUPERPUESTO',
                'errors' => ['fecha_ingreso' => [$e->getMessage()]],
            ], 422);
        } catch (ClaveDeOperacionRequerida $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'CLAVE_DE_OPERACION_REQUERIDA'], 422);
        } catch (ClaveDeOperacionReutilizada $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'CLAVE_DE_OPERACION_REUTILIZADA'], 409);
        } catch (IngresoNoVerificable $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'ESTRUCTURA_NO_VERIFICABLE'], 503);
        } catch (IngresosDeshabilitados $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'INGRESOS_DESHABILITADOS'], 503);
        }
    }
}
