<?php

namespace App\Modules\GestionPersonal\Presentation\Controllers;

use App\Modules\GestionPersonal\Domain\Exceptions\CierreNoProcede;
use App\Modules\GestionPersonal\Domain\Exceptions\ConflictoDeUnicidad;
use App\Modules\GestionPersonal\Domain\Exceptions\EntrevistaCerrada;
use App\Modules\GestionPersonal\Domain\Exceptions\EntrevistaSinResolver;
use App\Modules\GestionPersonal\Domain\Exceptions\EstadoNoPermitido;
use App\Modules\GestionPersonal\Domain\Exceptions\EvidenciaRequerida;
use App\Modules\GestionPersonal\Domain\Exceptions\ExpedienteModificado;
use App\Modules\GestionPersonal\Domain\Exceptions\ExpedienteNoEncontrado;
use App\Modules\GestionPersonal\Domain\Exceptions\FechaEfectivaInvalida;
use App\Modules\GestionPersonal\Domain\Exceptions\IncidenciaDeSalida;
use App\Modules\GestionPersonal\Domain\Exceptions\SalidaNoAplicable;
use App\Modules\GestionPersonal\Domain\Exceptions\SalidaYaFormalizada;
use App\Modules\GestionPersonal\Domain\Exceptions\SinPermisoDeEntrevistas;
use Closure;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Base de los controladores de offboarding: añade a la traducción de errores de movimientos y novedades las de
 * expedientes y entrevistas (contracts/api-offboarding.md). Un expediente inexistente, ajeno o de otro tenant responde
 * siempre igual.
 */
abstract class OffboardingBaseController extends ActuacionBaseController
{
    /**
     * @param  Closure(): JsonResponse  $accion
     */
    protected function responder(Closure $accion): JsonResponse
    {
        try {
            return $accion();
        } catch (ExpedienteNoEncontrado $e) {
            return $this->falla($e, 'EXPEDIENTE_NO_ENCONTRADO', 404);
        } catch (SalidaYaFormalizada $e) {
            return $this->falla($e, 'SALIDA_YA_FORMALIZADA', 409);
        } catch (FechaEfectivaInvalida $e) {
            return $this->falla($e, 'FECHA_EFECTIVA_INVALIDA', 422, ['fecha_efectiva' => [$e->getMessage()]]);
        } catch (EvidenciaRequerida $e) {
            return $this->falla($e, 'EVIDENCIA_REQUERIDA', 422, ['documento_ids' => [$e->getMessage()]]);
        } catch (CierreNoProcede $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'CIERRE_NO_PROCEDE', 'bloqueos' => $e->bloqueos], 422);
        } catch (EntrevistaSinResolver $e) {
            return $this->falla($e, 'ENTREVISTA_SIN_RESOLVER', 422);
        } catch (SinPermisoDeEntrevistas $e) {
            return $this->falla($e, 'SIN_PERMISO_DE_ENTREVISTAS', 403);
        } catch (ExpedienteModificado|ConflictoDeUnicidad $e) {
            return $this->falla($e, 'EXPEDIENTE_MODIFICADO', 409);
        } catch (EstadoNoPermitido $e) {
            return $this->falla($e, 'ESTADO_NO_PERMITIDO', 409);
        } catch (EntrevistaCerrada $e) {
            return $this->falla($e, 'ENTREVISTA_CERRADA', 409);
        } catch (SalidaNoAplicable $e) {
            return $this->falla($e, 'APLICACION_NO_PROCEDE', 409);
        } catch (IncidenciaDeSalida $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'INCIDENCIA_DE_APLICACION',
                'incidencia' => ['codigo' => $e->codigo, 'detalle' => $e->getMessage()],
            ], 409);
        } catch (Throwable $e) {
            return parent::responder(function () use ($e): JsonResponse {
                throw $e;
            });
        }
    }

    /**
     * @param  array<string, list<string>>|null  $errores
     */
    private function falla(Throwable $e, string $codigo, int $estado, ?array $errores = null): JsonResponse
    {
        $cuerpo = ['message' => $e->getMessage(), 'code' => $codigo];
        if ($errores !== null) {
            $cuerpo['errors'] = $errores;
        }

        return response()->json($cuerpo, $estado);
    }
}
