<?php

namespace App\Modules\Desarrollo\Presentation\Controllers;

use App\Modules\Desarrollo\Application\UseCases\ActualizarMicrocontenido;
use App\Modules\Desarrollo\Application\UseCases\CambiarEstadoMicro;
use App\Modules\Desarrollo\Application\UseCases\CrearMicrocontenido;
use App\Modules\Desarrollo\Application\UseCases\EliminarMicrocontenido;
use App\Modules\Desarrollo\Application\UseCases\ListarHistorialDeMicro;
use App\Modules\Desarrollo\Application\UseCases\ListarMicrocontenidos;
use App\Modules\Desarrollo\Application\UseCases\ObtenerMicrocontenido;
use App\Modules\Desarrollo\Application\UseCases\ObtenerOpcionesMicro;
use App\Modules\Desarrollo\Presentation\Requests\CambiarEstadoMicroRequest;
use App\Modules\Desarrollo\Presentation\Requests\GuardarMicrocontenidoRequest;
use App\Modules\Desarrollo\Presentation\Requests\ListarHistorialMicroRequest;
use App\Modules\Desarrollo\Presentation\Requests\ListarMicroRequest;
use App\Modules\Desarrollo\Presentation\Resources\FilaMicroResource;
use App\Modules\Desarrollo\Presentation\Resources\MicroResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class MicrocontenidoController
{
    use RespondeErroresDeMicro;

    public function index(ListarMicroRequest $request, ListarMicrocontenidos $listar): JsonResponse
    {
        // La capacidad `admin` del token decide la visibilidad (estados, opción correcta, completaciones).
        $listado = $listar->ejecutar($request->filtro(), $this->esAdmin($request), $this->usuario($request));

        $paginador = (new LengthAwarePaginator(
            $listado->items,
            $listado->total,
            $listado->porPagina,
            $listado->pagina,
            ['path' => $request->url()],
        ))->appends($request->query());

        return FilaMicroResource::collection($paginador)->response();
    }

    public function opciones(ObtenerOpcionesMicro $opciones): JsonResponse
    {
        return response()->json(['data' => $opciones->ejecutar()]);
    }

    public function show(int $id, Request $request, ObtenerMicrocontenido $obtener): JsonResponse
    {
        return $this->responder(fn () => (new MicroResource(
            $obtener->ejecutar($id, $this->esAdmin($request), $this->usuario($request)),
        ))->response());
    }

    public function store(GuardarMicrocontenidoRequest $request, CrearMicrocontenido $crear, ObtenerMicrocontenido $obtener): JsonResponse
    {
        return $this->responder(function () use ($request, $crear, $obtener) {
            $id = $crear->ejecutar($request->datos(), $this->usuario($request));

            return (new MicroResource($obtener->ejecutar($id, true, $this->usuario($request))))->response()->setStatusCode(201);
        });
    }

    public function update(int $id, GuardarMicrocontenidoRequest $request, ActualizarMicrocontenido $actualizar, ObtenerMicrocontenido $obtener): JsonResponse
    {
        return $this->responder(function () use ($id, $request, $actualizar, $obtener) {
            $actualizar->ejecutar($id, $request->datos(), $this->usuario($request));

            return (new MicroResource($obtener->ejecutar($id, true, $this->usuario($request))))->response();
        });
    }

    public function estado(int $id, CambiarEstadoMicroRequest $request, CambiarEstadoMicro $cambiar, ObtenerMicrocontenido $obtener): JsonResponse
    {
        return $this->responder(function () use ($id, $request, $cambiar, $obtener) {
            $cambiar->ejecutar($id, $request->estado(), $this->usuario($request));

            return (new MicroResource($obtener->ejecutar($id, true, $this->usuario($request))))->response();
        });
    }

    public function destroy(int $id, Request $request, EliminarMicrocontenido $eliminar): JsonResponse
    {
        return $this->responder(function () use ($id, $request, $eliminar) {
            $eliminar->ejecutar($id, $this->usuario($request));

            return response()->json(null, 204);
        });
    }

    public function historial(int $id, ListarHistorialMicroRequest $request, ListarHistorialDeMicro $listar): JsonResponse
    {
        return $this->responder(function () use ($id, $request, $listar) {
            $historial = $listar->ejecutar($id, $request->pagina(), $request->porPagina());

            $paginador = (new LengthAwarePaginator(
                $historial['items'],
                $historial['total'],
                $request->porPagina(),
                $request->pagina(),
                ['path' => $request->url()],
            ))->appends($request->query());

            return response()->json(['data' => $paginador->items(), 'meta' => $this->metaPaginacion($paginador)]);
        });
    }
}
