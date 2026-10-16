<?php

namespace App\Modules\GestionPersonal\Presentation\Controllers;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\Ports\ContextoDeTenantPort;
use App\Modules\GestionPersonal\Application\Services\ArmadorDeDesarrollo;
use App\Modules\GestionPersonal\Application\UseCases\ActualizarMovilidad;
use App\Modules\GestionPersonal\Application\UseCases\AgregarExperiencia;
use App\Modules\GestionPersonal\Application\UseCases\AgregarFormacion;
use App\Modules\GestionPersonal\Application\UseCases\CorregirExperiencia;
use App\Modules\GestionPersonal\Application\UseCases\CorregirFormacion;
use App\Modules\GestionPersonal\Application\UseCases\ObtenerDesarrollo;
use App\Modules\GestionPersonal\Domain\Exceptions\ColaboradorNoEncontrado;
use App\Modules\GestionPersonal\Domain\Exceptions\DatosInvalidos;
use App\Modules\GestionPersonal\Domain\Exceptions\EscrituraDeshabilitada;
use App\Modules\GestionPersonal\Domain\Exceptions\InformacionNoDisponible;
use App\Modules\GestionPersonal\Domain\Exceptions\RegistroModificado;
use App\Modules\GestionPersonal\Domain\Exceptions\RegistroNoEncontrado;
use App\Modules\GestionPersonal\Presentation\Requests\GuardarExperienciaRequest;
use App\Modules\GestionPersonal\Presentation\Requests\GuardarFormacionRequest;
use App\Modules\GestionPersonal\Presentation\Requests\GuardarMovilidadRequest;
use App\Modules\GestionPersonal\Presentation\Resources\DesarrolloResource;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DesarrolloController
{
    public function __construct(
        private readonly ContextoDeTenantPort $contextos,
        private readonly ArmadorDeDesarrollo $armador,
    ) {}

    public function show(Request $request, int $colaboradorId, ObtenerDesarrollo $obtener): JsonResponse
    {
        return $this->responder(fn () => (new DesarrolloResource(
            $obtener->ejecutar($this->contexto($request), $colaboradorId),
        ))->response());
    }

    public function storeFormacion(GuardarFormacionRequest $request, int $colaboradorId, AgregarFormacion $agregar): JsonResponse
    {
        return $this->responder(fn () => response()->json(['data' => $this->armador->formacion(
            $agregar->ejecutar($this->contexto($request), $this->actor($request), $colaboradorId, $request->datos()),
        )], 201));
    }

    public function updateFormacion(GuardarFormacionRequest $request, int $colaboradorId, int $formacionId, CorregirFormacion $corregir): JsonResponse
    {
        return $this->responder(fn () => response()->json(['data' => $this->armador->formacion(
            $corregir->ejecutar($this->contexto($request), $this->actor($request), $colaboradorId, $formacionId, $request->version(), $request->datos()),
        )]));
    }

    public function storeExperiencia(GuardarExperienciaRequest $request, int $colaboradorId, AgregarExperiencia $agregar): JsonResponse
    {
        return $this->responder(fn () => response()->json(['data' => $this->armador->experiencia(
            $agregar->ejecutar($this->contexto($request), $this->actor($request), $colaboradorId, $request->datos()),
        )], 201));
    }

    public function updateExperiencia(GuardarExperienciaRequest $request, int $colaboradorId, int $experienciaId, CorregirExperiencia $corregir): JsonResponse
    {
        return $this->responder(fn () => response()->json(['data' => $this->armador->experiencia(
            $corregir->ejecutar($this->contexto($request), $this->actor($request), $colaboradorId, $experienciaId, $request->version(), $request->datos()),
        )]));
    }

    public function updateMovilidad(GuardarMovilidadRequest $request, int $colaboradorId, ActualizarMovilidad $actualizar): JsonResponse
    {
        return $this->responder(fn () => response()->json(['data' => $this->armador->movilidad(
            $actualizar->ejecutar($this->contexto($request), $this->actor($request), $colaboradorId, $request->version(), $request->datos()),
        )]));
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
        } catch (RegistroNoEncontrado $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'REGISTRO_NO_ENCONTRADO'], 404);
        } catch (RegistroModificado $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'REGISTRO_MODIFICADO'], 409);
        } catch (DatosInvalidos $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'VALIDACION', 'errors' => $e->errores], 422);
        } catch (EscrituraDeshabilitada $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'ESCRITURA_DESHABILITADA'], 503);
        } catch (InformacionNoDisponible $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'INFORMACION_NO_DISPONIBLE'], 503);
        }
    }
}
