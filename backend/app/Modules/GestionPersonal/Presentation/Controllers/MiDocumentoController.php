<?php

namespace App\Modules\GestionPersonal\Presentation\Controllers;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\Ports\ContextoDeTenantPort;
use App\Modules\GestionPersonal\Application\UseCases\ListarMisDocumentos;
use App\Modules\GestionPersonal\Application\UseCases\ObtenerMiContenidoDocumento;
use App\Modules\GestionPersonal\Domain\Exceptions\AlmacenamientoNoDisponible;
use App\Modules\GestionPersonal\Domain\Exceptions\CuentaSinFicha;
use App\Modules\GestionPersonal\Domain\Exceptions\DocumentoNoEncontrado;
use App\Modules\GestionPersonal\Domain\Exceptions\InformacionNoDisponible;
use App\Modules\GestionPersonal\Domain\Exceptions\SinPermisoDeDocumentos;
use App\Modules\GestionPersonal\Presentation\Requests\ListarDocumentosRequest;
use App\Modules\GestionPersonal\Presentation\Resources\MiDocumentoResource;
use App\Modules\GestionPersonal\Presentation\Support\RespuestaDePdf;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Response;

class MiDocumentoController
{
    public function __construct(private readonly ContextoDeTenantPort $contextos) {}

    public function index(ListarDocumentosRequest $request, ListarMisDocumentos $listar): Response
    {
        return $this->responder($request, function ($contexto, $userId) use ($request, $listar) {
            $listado = $listar->ejecutar($contexto, $userId, $request->filtro());

            $paginador = (new LengthAwarePaginator(
                $listado->items,
                $listado->total,
                $listado->porPagina,
                $listado->pagina,
                ['path' => $request->url()],
            ))->appends($request->query());

            return MiDocumentoResource::collection($paginador)->response();
        });
    }

    public function contenido(Request $request, int $documento, ObtenerMiContenidoDocumento $obtener): Response
    {
        return $this->responder($request, fn ($contexto, $userId) => RespuestaDePdf::flujo(
            $obtener->ejecutar($contexto, $userId, $documento),
            $request->boolean('descarga'),
        ));
    }

    /** @param  Closure(ContextoDeTenant, int): Response  $accion */
    private function responder(Request $request, Closure $accion): Response
    {
        $userId = (int) $request->user()->getAuthIdentifier();
        $contexto = $this->contextos->deCuenta($userId);

        if ($contexto === null) {
            return $this->error((new CuentaSinFicha)->getMessage(), 'CUENTA_SIN_FICHA', 403);
        }

        try {
            return $accion($contexto, $userId);
        } catch (CuentaSinFicha $e) {
            return $this->error($e->getMessage(), 'CUENTA_SIN_FICHA', 403);
        } catch (SinPermisoDeDocumentos $e) {
            return $this->error($e->getMessage(), 'SIN_PERMISO_DOCUMENTOS', 403);
        } catch (DocumentoNoEncontrado $e) {
            return $this->error($e->getMessage(), 'DOCUMENTO_NO_ENCONTRADO', 404);
        } catch (AlmacenamientoNoDisponible $e) {
            return $this->error($e->getMessage(), 'ALMACENAMIENTO_NO_DISPONIBLE', 503);
        } catch (InformacionNoDisponible $e) {
            return $this->error($e->getMessage(), 'INFORMACION_NO_DISPONIBLE', 503);
        }
    }

    private function error(string $mensaje, string $codigo, int $estado): JsonResponse
    {
        return response()->json(['message' => $mensaje, 'code' => $codigo], $estado);
    }
}
