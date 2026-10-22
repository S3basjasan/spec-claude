<?php

namespace App\Modules\GestionPersonal\Presentation\Controllers;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\Ports\ContextoDeTenantPort;
use App\Modules\GestionPersonal\Domain\Exceptions\BorradorExistente;
use App\Modules\GestionPersonal\Domain\Exceptions\BorradorModificado;
use App\Modules\GestionPersonal\Domain\Exceptions\ClaveDeOperacionRequerida;
use App\Modules\GestionPersonal\Domain\Exceptions\ClaveDeOperacionReutilizada;
use App\Modules\GestionPersonal\Domain\Exceptions\DatosInvalidos;
use App\Modules\GestionPersonal\Domain\Exceptions\EscrituraDeshabilitada;
use App\Modules\GestionPersonal\Domain\Exceptions\InicioEnElPasado;
use App\Modules\GestionPersonal\Domain\Exceptions\PoliticaNoDisponible;
use App\Modules\GestionPersonal\Domain\Exceptions\PoliticaNoEncontrada;
use App\Modules\GestionPersonal\Domain\Exceptions\ProgramadaExistente;
use App\Modules\GestionPersonal\Domain\Exceptions\VersionNoRetirable;
use App\Modules\GestionPersonal\Domain\Exceptions\VigenciaIniciada;
use App\Modules\GestionPersonal\Domain\ValueObjects\TipoDePolitica;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Utilidades compartidas por los controladores de políticas: contexto, tipo de ruta y traducción de errores. */
abstract class PoliticaBaseController
{
    public function __construct(protected readonly ContextoDeTenantPort $contextos) {}

    protected function actor(Request $request): int
    {
        return (int) $request->user()->getAuthIdentifier();
    }

    protected function contexto(Request $request): ContextoDeTenant
    {
        return $this->contextos->deCuenta($this->actor($request)) ?? throw new PoliticaNoEncontrada;
    }

    protected function tipo(string $identificador): TipoDePolitica
    {
        return TipoDePolitica::desdeIdentificador($identificador) ?? throw new PoliticaNoEncontrada;
    }

    /**
     * Traduce las excepciones de dominio a respuestas HTTP sin revelar detalles internos.
     *
     * @param  Closure(): JsonResponse  $accion
     */
    protected function responder(Closure $accion): JsonResponse
    {
        try {
            return $accion();
        } catch (PoliticaNoEncontrada $e) {
            return $this->error($e->getMessage(), 'POLITICA_NO_ENCONTRADA', 404);
        } catch (BorradorExistente $e) {
            return $this->error($e->getMessage(), 'BORRADOR_EXISTENTE', 409, ['borrador_id' => $e->borradorId]);
        } catch (BorradorModificado $e) {
            return $this->error($e->getMessage(), 'BORRADOR_MODIFICADO', 409);
        } catch (ProgramadaExistente $e) {
            return $this->error($e->getMessage(), 'PROGRAMADA_EXISTENTE', 409);
        } catch (VigenciaIniciada $e) {
            return $this->error($e->getMessage(), 'VIGENCIA_INICIADA', 409);
        } catch (VersionNoRetirable $e) {
            return $this->error($e->getMessage(), 'VERSION_NO_RETIRABLE', 409);
        } catch (ClaveDeOperacionReutilizada $e) {
            return $this->error($e->getMessage(), 'CLAVE_DE_OPERACION_REUTILIZADA', 409);
        } catch (ClaveDeOperacionRequerida $e) {
            return $this->error($e->getMessage(), 'CLAVE_DE_OPERACION_REQUERIDA', 422);
        } catch (InicioEnElPasado $e) {
            return $this->error($e->getMessage(), 'INICIO_EN_EL_PASADO', 422, ['inicio.fecha_hora_local' => [$e->getMessage()]]);
        } catch (DatosInvalidos $e) {
            return $this->error($e->getMessage(), 'VALIDACION', 422, $e->errores);
        } catch (EscrituraDeshabilitada $e) {
            return $this->error($e->getMessage(), 'ESCRITURA_DESHABILITADA', 503);
        } catch (PoliticaNoDisponible $e) {
            return $this->error($e->getMessage(), 'POLITICA_NO_DISPONIBLE', 503);
        }
    }

    /** @param array<string, mixed>|null $errores */
    protected function error(string $mensaje, string $codigo, int $estado, ?array $errores = null): JsonResponse
    {
        $cuerpo = ['message' => $mensaje, 'code' => $codigo];
        if ($errores !== null) {
            $cuerpo['errors'] = $errores;
        }

        return response()->json($cuerpo, $estado);
    }
}
