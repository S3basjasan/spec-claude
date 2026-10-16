<?php

namespace App\Modules\GestionPersonal\Presentation\Controllers;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\Ports\ContextoDeTenantPort;
use App\Modules\GestionPersonal\Application\UseCases\CambiarEstadoCategoria;
use App\Modules\GestionPersonal\Application\UseCases\CrearCategoria;
use App\Modules\GestionPersonal\Application\UseCases\EditarCategoria;
use App\Modules\GestionPersonal\Application\UseCases\ListarCategorias;
use App\Modules\GestionPersonal\Application\UseCases\ObtenerCategoria;
use App\Modules\GestionPersonal\Domain\Exceptions\CategoriaModificada;
use App\Modules\GestionPersonal\Domain\Exceptions\CategoriaNoEncontrada;
use App\Modules\GestionPersonal\Domain\Exceptions\DatosInvalidos;
use App\Modules\GestionPersonal\Domain\Exceptions\EscrituraDeshabilitada;
use App\Modules\GestionPersonal\Presentation\Requests\CambiarEstadoCategoriaRequest;
use App\Modules\GestionPersonal\Presentation\Requests\GuardarCategoriaRequest;
use App\Modules\GestionPersonal\Presentation\Requests\ListarCategoriasRequest;
use App\Modules\GestionPersonal\Presentation\Resources\CategoriaDocumentalResource;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class CategoriaDocumentalController
{
    public function __construct(private readonly ContextoDeTenantPort $contextos) {}

    public function index(ListarCategoriasRequest $request, ListarCategorias $listar): JsonResponse
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

            return CategoriaDocumentalResource::collection($paginador)->response();
        });
    }

    public function show(Request $request, int $categoria, ObtenerCategoria $obtener): JsonResponse
    {
        return $this->responder(fn () => (new CategoriaDocumentalResource(
            $obtener->ejecutar($this->contexto($request), $categoria),
        ))->response());
    }

    public function store(GuardarCategoriaRequest $request, CrearCategoria $crear): JsonResponse
    {
        return $this->responder(fn () => (new CategoriaDocumentalResource(
            $crear->ejecutar($this->contexto($request), $this->actor($request), $request->datos()),
        ))->response()->setStatusCode(201));
    }

    public function update(GuardarCategoriaRequest $request, int $categoria, EditarCategoria $editar): JsonResponse
    {
        return $this->responder(fn () => (new CategoriaDocumentalResource(
            $editar->ejecutar($this->contexto($request), $this->actor($request), $categoria, $request->version(), $request->datos()),
        ))->response());
    }

    public function updateEstado(CambiarEstadoCategoriaRequest $request, int $categoria, CambiarEstadoCategoria $cambiar): JsonResponse
    {
        return $this->responder(fn () => (new CategoriaDocumentalResource(
            $cambiar->ejecutar($this->contexto($request), $this->actor($request), $categoria, $request->version(), $request->estado()),
        ))->response());
    }

    private function actor(Request $request): int
    {
        return (int) $request->user()->getAuthIdentifier();
    }

    private function contexto(Request $request): ContextoDeTenant
    {
        return $this->contextos->deCuenta($this->actor($request)) ?? throw new CategoriaNoEncontrada;
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
        } catch (CategoriaNoEncontrada $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'CATEGORIA_NO_ENCONTRADA'], 404);
        } catch (CategoriaModificada $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'CATEGORIA_MODIFICADA'], 409);
        } catch (DatosInvalidos $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'VALIDACION', 'errors' => $e->errores], 422);
        } catch (EscrituraDeshabilitada $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'ESCRITURA_DESHABILITADA'], 503);
        }
    }
}
