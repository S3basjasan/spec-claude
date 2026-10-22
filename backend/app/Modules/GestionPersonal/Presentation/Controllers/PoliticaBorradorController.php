<?php

namespace App\Modules\GestionPersonal\Presentation\Controllers;

use App\Modules\GestionPersonal\Application\UseCases\CrearBorrador;
use App\Modules\GestionPersonal\Application\UseCases\DescartarBorrador;
use App\Modules\GestionPersonal\Application\UseCases\EditarBorrador;
use App\Modules\GestionPersonal\Application\UseCases\PrevisualizarPublicacion;
use App\Modules\GestionPersonal\Application\UseCases\PublicarVersion;
use App\Modules\GestionPersonal\Presentation\Requests\CrearBorradorRequest;
use App\Modules\GestionPersonal\Presentation\Requests\EditarBorradorRequest;
use App\Modules\GestionPersonal\Presentation\Requests\PrevisualizarPublicacionRequest;
use App\Modules\GestionPersonal\Presentation\Requests\PublicarVersionRequest;
use App\Modules\GestionPersonal\Presentation\Requests\VersionDeBorradorRequest;
use App\Modules\GestionPersonal\Presentation\Resources\PrevisualizacionResource;
use App\Modules\GestionPersonal\Presentation\Resources\VersionResource;
use Illuminate\Http\JsonResponse;

/** Ciclo del borrador: crear, editar, descartar, previsualizar y publicar. */
class PoliticaBorradorController extends PoliticaBaseController
{
    public function store(CrearBorradorRequest $request, string $tipo, CrearBorrador $crear): JsonResponse
    {
        return $this->responder(fn () => (new VersionResource(
            $crear->ejecutar($this->contexto($request), $this->actor($request), $this->tipo($tipo), $request->baseVersionId()),
        ))->response()->setStatusCode(201));
    }

    public function update(EditarBorradorRequest $request, string $tipo, int $version, EditarBorrador $editar): JsonResponse
    {
        return $this->responder(fn () => (new VersionResource(
            $editar->ejecutar(
                $this->contexto($request), $this->actor($request), $this->tipo($tipo), $version,
                $request->version(), $request->descripcion(), $request->parametros(),
            ),
        ))->response());
    }

    public function descartar(VersionDeBorradorRequest $request, string $tipo, int $version, DescartarBorrador $descartar): JsonResponse
    {
        return $this->responder(function () use ($request, $tipo, $version, $descartar) {
            $descartar->ejecutar($this->contexto($request), $this->actor($request), $this->tipo($tipo), $version, $request->version());

            return response()->json(['data' => null]);
        });
    }

    public function previsualizar(PrevisualizarPublicacionRequest $request, string $tipo, int $version, PrevisualizarPublicacion $previsualizar): JsonResponse
    {
        return $this->responder(fn () => (new PrevisualizacionResource(
            $previsualizar->ejecutar($this->contexto($request), $this->tipo($tipo), $version, $request->inicio()),
        ))->response());
    }

    public function publicar(PublicarVersionRequest $request, string $tipo, int $version, PublicarVersion $publicar): JsonResponse
    {
        return $this->responder(function () use ($request, $tipo, $version, $publicar) {
            $resultado = $publicar->ejecutar($this->contexto($request), $this->actor($request), $this->tipo($tipo), $version, $request->datos());

            return (new VersionResource($resultado['version']))->response()->setStatusCode($resultado['repetida'] ? 200 : 201);
        });
    }
}
