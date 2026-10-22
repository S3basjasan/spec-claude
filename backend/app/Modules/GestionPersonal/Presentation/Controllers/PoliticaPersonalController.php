<?php

namespace App\Modules\GestionPersonal\Presentation\Controllers;

use App\Modules\GestionPersonal\Application\UseCases\CompararVersion;
use App\Modules\GestionPersonal\Application\UseCases\ListarPoliticas;
use App\Modules\GestionPersonal\Application\UseCases\ObtenerPolitica;
use App\Modules\GestionPersonal\Application\UseCases\ObtenerVersion;
use App\Modules\GestionPersonal\Application\UseCases\RetirarVersion;
use App\Modules\GestionPersonal\Presentation\Requests\ListarPoliticasRequest;
use App\Modules\GestionPersonal\Presentation\Requests\RetirarVersionRequest;
use App\Modules\GestionPersonal\Presentation\Resources\PoliticaDetalleResource;
use App\Modules\GestionPersonal\Presentation\Resources\PoliticaResumenResource;
use App\Modules\GestionPersonal\Presentation\Resources\VersionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Consulta administrativa de políticas (listado, detalle, versiones, comparación) y retiro de versiones programadas. */
class PoliticaPersonalController extends PoliticaBaseController
{
    public function index(ListarPoliticasRequest $request, ListarPoliticas $listar): JsonResponse
    {
        return $this->responder(fn () => PoliticaResumenResource::collection(
            $listar->ejecutar($this->contexto($request), $request->tipo(), $request->situacion()),
        )->response());
    }

    public function show(Request $request, string $tipo, ObtenerPolitica $obtener): JsonResponse
    {
        return $this->responder(fn () => (new PoliticaDetalleResource(
            $obtener->ejecutar($this->contexto($request), $this->tipo($tipo)),
        ))->response());
    }

    public function version(Request $request, string $tipo, int $version, ObtenerVersion $obtener): JsonResponse
    {
        return $this->responder(fn () => (new VersionResource(
            $obtener->ejecutar($this->contexto($request), $this->tipo($tipo), $version),
        ))->response());
    }

    public function comparacion(Request $request, string $tipo, int $version, CompararVersion $comparar): JsonResponse
    {
        return $this->responder(function () use ($request, $tipo, $version, $comparar) {
            $resultado = $comparar->ejecutar($this->contexto($request), $this->tipo($tipo), $version);

            return response()->json(['data' => [
                'contra' => $resultado['contra'] === null ? null : (new VersionResource($resultado['contra']))->toArray($request),
                'diferencias' => $resultado['diferencias'],
            ]]);
        });
    }

    public function retirar(RetirarVersionRequest $request, string $tipo, int $version, RetirarVersion $retirar): JsonResponse
    {
        return $this->responder(function () use ($request, $tipo, $version, $retirar) {
            $resultado = $retirar->ejecutar(
                $this->contexto($request), $this->actor($request), $this->tipo($tipo), $version,
                $request->justificacion(), $request->header('Idempotency-Key'),
            );

            return (new VersionResource($resultado['version']))->response();
        });
    }
}
