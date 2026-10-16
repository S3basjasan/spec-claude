<?php

namespace App\Modules\GestionPersonal\Presentation\Controllers;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\Ports\ContextoDeTenantPort;
use App\Modules\GestionPersonal\Application\UseCases\ConsultarSituacionLaboral;
use App\Modules\GestionPersonal\Application\UseCases\ConsultarTrayectoria;
use App\Modules\GestionPersonal\Application\UseCases\ResolverFichaPropia;
use App\Modules\GestionPersonal\Domain\Exceptions\ColaboradorNoEncontrado;
use App\Modules\GestionPersonal\Domain\Exceptions\CuentaSinFicha;
use App\Modules\GestionPersonal\Domain\Exceptions\SinPermisoDeFicha;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SituacionLaboralController
{
    public function __construct(private readonly ContextoDeTenantPort $contextos) {}

    public function situacion(Request $request, int $colaboradorId, ConsultarSituacionLaboral $consultar): JsonResponse
    {
        return $this->responder(fn () => response()->json(['data' => $consultar->ejecutar($this->contexto($request), $colaboradorId)]));
    }

    public function trayectoria(Request $request, int $colaboradorId, ConsultarTrayectoria $consultar): JsonResponse
    {
        return $this->responder(fn () => response()->json(['data' => $consultar->ejecutar($this->contexto($request), $colaboradorId)]));
    }

    public function miSituacion(Request $request, ResolverFichaPropia $resolver, ConsultarSituacionLaboral $consultar): JsonResponse
    {
        return $this->responder(function () use ($request, $resolver, $consultar) {
            $contexto = $this->contexto($request);
            $id = $resolver->ejecutar($contexto, (int) $request->user()->getAuthIdentifier());

            return response()->json(['data' => $consultar->ejecutar($contexto, $id)]);
        });
    }

    public function miTrayectoria(Request $request, ResolverFichaPropia $resolver, ConsultarTrayectoria $consultar): JsonResponse
    {
        return $this->responder(function () use ($request, $resolver, $consultar) {
            $contexto = $this->contexto($request);
            $id = $resolver->ejecutar($contexto, (int) $request->user()->getAuthIdentifier());

            return response()->json(['data' => $consultar->ejecutar($contexto, $id)]);
        });
    }

    private function contexto(Request $request): ContextoDeTenant
    {
        return $this->contextos->deCuenta((int) $request->user()->getAuthIdentifier()) ?? throw new ColaboradorNoEncontrado;
    }

    /**
     * @param  Closure(): JsonResponse  $accion
     */
    private function responder(Closure $accion): JsonResponse
    {
        try {
            return $accion();
        } catch (ColaboradorNoEncontrado $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'FICHA_NO_ENCONTRADA'], 404);
        } catch (CuentaSinFicha $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'CUENTA_SIN_FICHA'], 403);
        } catch (SinPermisoDeFicha $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'SIN_PERMISO_FICHA'], 403);
        }
    }
}
