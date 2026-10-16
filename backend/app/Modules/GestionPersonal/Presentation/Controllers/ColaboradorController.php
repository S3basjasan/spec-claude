<?php

namespace App\Modules\GestionPersonal\Presentation\Controllers;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\Ports\ContextoDeTenantPort;
use App\Modules\GestionPersonal\Application\UseCases\ActualizarFicha;
use App\Modules\GestionPersonal\Application\UseCases\ListarColaboradores;
use App\Modules\GestionPersonal\Application\UseCases\ObtenerColaborador;
use App\Modules\GestionPersonal\Application\UseCases\ObtenerFiltrosDeColaboradores;
use App\Modules\GestionPersonal\Application\UseCases\RegistrarFicha;
use App\Modules\GestionPersonal\Domain\Exceptions\ClaveDeOperacionRequerida;
use App\Modules\GestionPersonal\Domain\Exceptions\ClaveDeOperacionReutilizada;
use App\Modules\GestionPersonal\Domain\Exceptions\ColaboradorNoEncontrado;
use App\Modules\GestionPersonal\Domain\Exceptions\DatosInvalidos;
use App\Modules\GestionPersonal\Domain\Exceptions\EscrituraDeshabilitada;
use App\Modules\GestionPersonal\Domain\Exceptions\FichaModificada;
use App\Modules\GestionPersonal\Domain\Exceptions\IdentificacionDuplicada;
use App\Modules\GestionPersonal\Domain\Exceptions\InformacionNoDisponible;
use App\Modules\GestionPersonal\Presentation\Requests\GuardarColaboradorRequest;
use App\Modules\GestionPersonal\Presentation\Requests\ListarColaboradoresRequest;
use App\Modules\GestionPersonal\Presentation\Resources\ColaboradorDetalleResource;
use App\Modules\GestionPersonal\Presentation\Resources\ColaboradorResumenResource;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ColaboradorController
{
    public function __construct(private readonly ContextoDeTenantPort $contextos) {}

    public function index(ListarColaboradoresRequest $request, ListarColaboradores $listar): JsonResponse
    {
        return $this->responder(function () use ($request, $listar) {
            $listado = $listar->ejecutar($this->contexto($request), $request->filtro());

            $paginador = (new LengthAwarePaginator(
                $listado->items,
                $listado->total,
                $listado->porPagina,
                $listado->pagina,
                ['path' => $request->url()],
            ))->appends($request->query());

            return ColaboradorResumenResource::collection($paginador)->response();
        });
    }

    public function filtros(Request $request, ObtenerFiltrosDeColaboradores $filtros): JsonResponse
    {
        return $this->responder(fn () => response()->json(['data' => $filtros->ejecutar($this->contexto($request))]));
    }

    public function show(Request $request, int $colaboradorId, ObtenerColaborador $obtener): JsonResponse
    {
        return $this->responder(fn () => (new ColaboradorDetalleResource(
            $obtener->ejecutar($this->contexto($request), $colaboradorId),
        ))->response());
    }

    public function store(GuardarColaboradorRequest $request, RegistrarFicha $registrar): JsonResponse
    {
        return $this->responder(function () use ($request, $registrar) {
            $resultado = $registrar->ejecutar(
                $this->contexto($request),
                $this->actor($request),
                $request->header('Idempotency-Key'),
                $request->datos(),
            );

            return (new ColaboradorDetalleResource($resultado))->response()->setStatusCode($resultado->creado ? 201 : 200);
        });
    }

    public function update(GuardarColaboradorRequest $request, int $colaboradorId, ActualizarFicha $actualizar): JsonResponse
    {
        return $this->responder(fn () => (new ColaboradorDetalleResource(
            $actualizar->ejecutar(
                $this->contexto($request),
                $this->actor($request),
                $colaboradorId,
                (int) $request->validated('version'),
                $request->datos(),
            ),
        ))->response());
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
        } catch (FichaModificada $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'FICHA_MODIFICADA'], 409);
        } catch (IdentificacionDuplicada $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'IDENTIFICACION_DUPLICADA',
                'errors' => ['numero_identificacion' => [$e->getMessage()]],
                'ficha_existente' => ['id' => $e->fichaId, 'codigo' => $e->fichaCodigo],
            ], 422);
        } catch (DatosInvalidos $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'VALIDACION', 'errors' => $e->errores], 422);
        } catch (ClaveDeOperacionRequerida $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'CLAVE_DE_OPERACION_REQUERIDA'], 422);
        } catch (ClaveDeOperacionReutilizada $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'CLAVE_DE_OPERACION_REUTILIZADA'], 422);
        } catch (EscrituraDeshabilitada $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'ESCRITURA_DESHABILITADA'], 503);
        } catch (InformacionNoDisponible $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'INFORMACION_NO_DISPONIBLE'], 503);
        }
    }
}
