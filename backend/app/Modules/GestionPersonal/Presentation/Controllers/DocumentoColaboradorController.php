<?php

namespace App\Modules\GestionPersonal\Presentation\Controllers;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\Ports\ContextoDeTenantPort;
use App\Modules\GestionPersonal\Application\UseCases\ArchivarDocumento;
use App\Modules\GestionPersonal\Application\UseCases\CambiarVisibilidadDocumento;
use App\Modules\GestionPersonal\Application\UseCases\CargarDocumento;
use App\Modules\GestionPersonal\Application\UseCases\ListarDocumentos;
use App\Modules\GestionPersonal\Application\UseCases\ObtenerContenidoDocumento;
use App\Modules\GestionPersonal\Domain\Exceptions\AlmacenamientoNoDisponible;
use App\Modules\GestionPersonal\Domain\Exceptions\ArchivoInvalido;
use App\Modules\GestionPersonal\Domain\Exceptions\CategoriaInactiva;
use App\Modules\GestionPersonal\Domain\Exceptions\ClaveDeOperacionRequerida;
use App\Modules\GestionPersonal\Domain\Exceptions\ClaveDeOperacionReutilizada;
use App\Modules\GestionPersonal\Domain\Exceptions\ColaboradorNoEncontrado;
use App\Modules\GestionPersonal\Domain\Exceptions\ConflictoDeUnicidad;
use App\Modules\GestionPersonal\Domain\Exceptions\CuentaSinFicha;
use App\Modules\GestionPersonal\Domain\Exceptions\DatosInvalidos;
use App\Modules\GestionPersonal\Domain\Exceptions\DocumentoModificado;
use App\Modules\GestionPersonal\Domain\Exceptions\DocumentoNoEncontrado;
use App\Modules\GestionPersonal\Domain\Exceptions\EscrituraDeshabilitada;
use App\Modules\GestionPersonal\Domain\Exceptions\InformacionNoDisponible;
use App\Modules\GestionPersonal\Domain\Exceptions\SinPermisoDeDocumentos;
use App\Modules\GestionPersonal\Domain\Exceptions\ValidacionNoDisponible;
use App\Modules\GestionPersonal\Presentation\Requests\ArchivarDocumentoRequest;
use App\Modules\GestionPersonal\Presentation\Requests\CambiarVisibilidadRequest;
use App\Modules\GestionPersonal\Presentation\Requests\CargarDocumentoRequest;
use App\Modules\GestionPersonal\Presentation\Requests\ListarDocumentosRequest;
use App\Modules\GestionPersonal\Presentation\Resources\DocumentoResource;
use App\Modules\GestionPersonal\Presentation\Support\RespuestaDePdf;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Response;

class DocumentoColaboradorController
{
    public function __construct(private readonly ContextoDeTenantPort $contextos) {}

    public function index(ListarDocumentosRequest $request, int $colaborador, ListarDocumentos $listar): Response
    {
        return $this->responder(function () use ($request, $colaborador, $listar) {
            $listado = $listar->ejecutar($this->contexto($request), $colaborador, $request->filtro());

            $paginador = (new LengthAwarePaginator(
                $listado->items,
                $listado->total,
                $listado->porPagina,
                $listado->pagina,
                ['path' => $request->url()],
            ))->appends($request->query());

            return DocumentoResource::collection($paginador)->response();
        });
    }

    public function store(CargarDocumentoRequest $request, int $colaborador, CargarDocumento $cargar): Response
    {
        return $this->responder(function () use ($request, $colaborador, $cargar) {
            $resultado = $cargar->ejecutar(
                $this->contexto($request),
                $this->actor($request),
                $colaborador,
                $request->header('Idempotency-Key'),
                $request->carga(),
            );

            return (new DocumentoResource($resultado))->response()->setStatusCode($resultado->creado ? 201 : 200);
        });
    }

    public function contenido(Request $request, int $colaborador, int $documento, ObtenerContenidoDocumento $obtener): Response
    {
        return $this->responder(fn () => RespuestaDePdf::flujo(
            $obtener->ejecutar($this->contexto($request), $colaborador, $documento),
            $request->boolean('descarga'),
        ));
    }

    public function updateVisibilidad(CambiarVisibilidadRequest $request, int $colaborador, int $documento, CambiarVisibilidadDocumento $cambiar): Response
    {
        return $this->responder(fn () => (new DocumentoResource(
            $cambiar->ejecutar($this->contexto($request), $this->actor($request), $colaborador, $documento, $request->version(), $request->visible()),
        ))->response());
    }

    public function archivar(ArchivarDocumentoRequest $request, int $colaborador, int $documento, ArchivarDocumento $archivar): Response
    {
        return $this->responder(fn () => (new DocumentoResource(
            $archivar->ejecutar($this->contexto($request), $this->actor($request), $colaborador, $documento, $request->version()),
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
     * @param  Closure(): Response  $accion
     */
    private function responder(Closure $accion): Response
    {
        try {
            return $accion();
        } catch (ColaboradorNoEncontrado $e) {
            return $this->error($e->getMessage(), 'FICHA_NO_ENCONTRADA', 404);
        } catch (DocumentoNoEncontrado $e) {
            return $this->error($e->getMessage(), 'DOCUMENTO_NO_ENCONTRADO', 404);
        } catch (DocumentoModificado $e) {
            return $this->error($e->getMessage(), 'DOCUMENTO_MODIFICADO', 409);
        } catch (CategoriaInactiva $e) {
            return $this->error($e->getMessage(), 'CATEGORIA_INACTIVA', 409);
        } catch (ClaveDeOperacionReutilizada $e) {
            return $this->error($e->getMessage(), 'CLAVE_DE_OPERACION_REUTILIZADA', 409);
        } catch (ConflictoDeUnicidad $e) {
            return $this->error($e->getMessage(), 'DOCUMENTO_MODIFICADO', 409);
        } catch (ClaveDeOperacionRequerida $e) {
            return $this->error($e->getMessage(), 'CLAVE_DE_OPERACION_REQUERIDA', 422);
        } catch (ArchivoInvalido $e) {
            return response()->json([
                'message' => 'Los datos no son válidos.',
                'code' => 'VALIDACION',
                'errors' => ['archivo' => [$e->getMessage()]],
                'motivo' => $e->motivo,
            ], 422);
        } catch (DatosInvalidos $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'VALIDACION', 'errors' => $e->errores], 422);
        } catch (EscrituraDeshabilitada $e) {
            return $this->error($e->getMessage(), 'ESCRITURA_DESHABILITADA', 503);
        } catch (AlmacenamientoNoDisponible|ValidacionNoDisponible $e) {
            return $this->error($e->getMessage(), 'ALMACENAMIENTO_NO_DISPONIBLE', 503);
        } catch (InformacionNoDisponible $e) {
            return $this->error($e->getMessage(), 'INFORMACION_NO_DISPONIBLE', 503);
        } catch (CuentaSinFicha|SinPermisoDeDocumentos $e) {
            return $this->error($e->getMessage(), 'SIN_PERMISO_DOCUMENTOS', 403);
        }
    }

    private function error(string $mensaje, string $codigo, int $estado): JsonResponse
    {
        return response()->json(['message' => $mensaje, 'code' => $codigo], $estado);
    }
}
