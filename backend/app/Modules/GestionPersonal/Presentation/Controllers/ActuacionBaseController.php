<?php

namespace App\Modules\GestionPersonal\Presentation\Controllers;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\Ports\ContextoDeTenantPort;
use App\Modules\GestionPersonal\Domain\Exceptions\AnticipacionInsuficiente;
use App\Modules\GestionPersonal\Domain\Exceptions\AplicacionNoProcede;
use App\Modules\GestionPersonal\Domain\Exceptions\AusenciaFutura;
use App\Modules\GestionPersonal\Domain\Exceptions\ClaveDeOperacionRequerida;
use App\Modules\GestionPersonal\Domain\Exceptions\ClaveDeOperacionReutilizada;
use App\Modules\GestionPersonal\Domain\Exceptions\ColaboradorNoEncontrado;
use App\Modules\GestionPersonal\Domain\Exceptions\ConflictoDeUnicidad;
use App\Modules\GestionPersonal\Domain\Exceptions\CruceSinReconocer;
use App\Modules\GestionPersonal\Domain\Exceptions\CuentaSinFicha;
use App\Modules\GestionPersonal\Domain\Exceptions\DatosInvalidos;
use App\Modules\GestionPersonal\Domain\Exceptions\DocumentoRequerido;
use App\Modules\GestionPersonal\Domain\Exceptions\EstadoNoResoluble;
use App\Modules\GestionPersonal\Domain\Exceptions\EvaluacionNoVerificable;
use App\Modules\GestionPersonal\Domain\Exceptions\FechaPasada;
use App\Modules\GestionPersonal\Domain\Exceptions\IncidenciaDeAplicacion;
use App\Modules\GestionPersonal\Domain\Exceptions\IngresoNoVerificable;
use App\Modules\GestionPersonal\Domain\Exceptions\JustificacionRequerida;
use App\Modules\GestionPersonal\Domain\Exceptions\MovimientoIncompatible;
use App\Modules\GestionPersonal\Domain\Exceptions\PoliticaNoAdmiteSolicitudes;
use App\Modules\GestionPersonal\Domain\Exceptions\PoliticaNoConfigurada;
use App\Modules\GestionPersonal\Domain\Exceptions\PoliticaNoDisponible;
use App\Modules\GestionPersonal\Domain\Exceptions\PosicionNoDisponible;
use App\Modules\GestionPersonal\Domain\Exceptions\SinVinculoVigente;
use App\Modules\GestionPersonal\Domain\Exceptions\SolicitudModificada;
use App\Modules\GestionPersonal\Domain\Exceptions\SolicitudNoEncontrada;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Base de los controladores de movimientos y novedades: resuelve el tenant desde la cuenta autenticada (nunca desde
 * el cliente) y traduce las excepciones de dominio a respuestas seguras (contracts/api-movimientos.md).
 */
abstract class ActuacionBaseController
{
    public function __construct(protected readonly ContextoDeTenantPort $contextos) {}

    protected function actor(Request $request): int
    {
        return (int) $request->user()->getAuthIdentifier();
    }

    protected function contexto(Request $request): ContextoDeTenant
    {
        return $this->contextos->deCuenta($this->actor($request)) ?? throw new SolicitudNoEncontrada;
    }

    /**
     * @param  Closure(): JsonResponse  $accion
     */
    protected function responder(Closure $accion): JsonResponse
    {
        try {
            return $accion();
        } catch (SolicitudNoEncontrada $e) {
            return $this->error($e, 'SOLICITUD_NO_ENCONTRADA', 404);
        } catch (ColaboradorNoEncontrado $e) {
            return $this->error($e, 'FICHA_NO_ENCONTRADA', 404);
        } catch (CuentaSinFicha $e) {
            return $this->error($e, 'SIN_FICHA_ASOCIADA', 404);
        } catch (DatosInvalidos $e) {
            return $this->error($e, 'VALIDACION', 422, $e->errores);
        } catch (AnticipacionInsuficiente $e) {
            return $this->error($e, 'ANTICIPACION_INSUFICIENTE', 422, ['fecha_inicio' => [$e->getMessage()]]);
        } catch (FechaPasada $e) {
            return $this->error($e, 'FECHA_PASADA', 422, ['fecha_inicio' => [$e->getMessage()]]);
        } catch (AusenciaFutura $e) {
            return $this->error($e, 'AUSENCIA_FUTURA', 422, ['fecha_fin' => [$e->getMessage()]]);
        } catch (JustificacionRequerida $e) {
            return $this->error($e, 'JUSTIFICACION_REQUERIDA', 422, ['motivo' => [$e->getMessage()]]);
        } catch (DocumentoRequerido $e) {
            return $this->error($e, 'DOCUMENTO_REQUERIDO', 422, ['documento_ids' => [$e->getMessage()]]);
        } catch (SinVinculoVigente $e) {
            return $this->error($e, 'SIN_VINCULO_VIGENTE', 422);
        } catch (PosicionNoDisponible $e) {
            return $this->error($e, 'POSICION_NO_DISPONIBLE', 422, ['posicion_destino_id' => [$e->getMessage()]]);
        } catch (CruceSinReconocer $e) {
            return $this->error($e, 'CRUCE_SIN_RECONOCER', 422, ['reconoce_cruce' => [$e->getMessage()]]);
        } catch (EvaluacionNoVerificable $e) {
            return $this->error($e, 'EVALUACION_NO_VERIFICABLE', 422);
        } catch (PoliticaNoConfigurada $e) {
            return $this->error($e, 'POLITICA_SIN_CONFIGURAR', 422);
        } catch (PoliticaNoAdmiteSolicitudes $e) {
            return $this->error($e, 'POLITICA_NO_ADMITE_SOLICITUDES', 422);
        } catch (ClaveDeOperacionRequerida $e) {
            return $this->error($e, 'CLAVE_DE_OPERACION_REQUERIDA', 422);
        } catch (ClaveDeOperacionReutilizada $e) {
            return $this->error($e, 'CLAVE_DE_OPERACION_REUTILIZADA', 409);
        } catch (SolicitudModificada $e) {
            return $this->error($e, 'SOLICITUD_MODIFICADA', 409);
        } catch (EstadoNoResoluble $e) {
            return $this->error($e, 'ESTADO_NO_RESOLUBLE', 409);
        } catch (MovimientoIncompatible $e) {
            return $this->error($e, 'MOVIMIENTO_INCOMPATIBLE', 409);
        } catch (AplicacionNoProcede $e) {
            return $this->error($e, 'APLICACION_NO_PROCEDE', 409);
        } catch (IncidenciaDeAplicacion $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'INCIDENCIA_DE_APLICACION',
                'incidencia' => ['codigo' => $e->codigo, 'detalle' => $e->getMessage()],
            ], 409);
        } catch (ConflictoDeUnicidad $e) {
            return $this->error($e, 'SOLICITUD_MODIFICADA', 409);
        } catch (PoliticaNoDisponible $e) {
            return $this->error($e, 'POLITICA_NO_DISPONIBLE', 503);
        } catch (IngresoNoVerificable $e) {
            return $this->error($e, 'ESTRUCTURA_NO_VERIFICABLE', 503);
        }
    }

    /**
     * @param  array<string, list<string>>|null  $errores
     */
    private function error(\Throwable $e, string $codigo, int $estado, ?array $errores = null): JsonResponse
    {
        $cuerpo = ['message' => $e->getMessage(), 'code' => $codigo];
        if ($errores !== null) {
            $cuerpo['errors'] = $errores;
        }

        return response()->json($cuerpo, $estado);
    }
}
