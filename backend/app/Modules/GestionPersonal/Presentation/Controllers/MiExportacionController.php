<?php

namespace App\Modules\GestionPersonal\Presentation\Controllers;

use App\Modules\GestionPersonal\Application\Ports\ContextoDeTenantPort;
use App\Modules\GestionPersonal\Application\UseCases\GenerarMiFichaPdf;
use App\Modules\GestionPersonal\Domain\Exceptions\ColaboradorNoEncontrado;
use App\Modules\GestionPersonal\Domain\Exceptions\CuentaSinFicha;
use App\Modules\GestionPersonal\Domain\Exceptions\ExportacionNoDisponible;
use App\Modules\GestionPersonal\Domain\Exceptions\SinPermisoDeExportacion;
use App\Modules\GestionPersonal\Presentation\Support\RespuestaDePdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Exportación de la ficha propia. No acepta ningún identificador: la ficha sale de la asociación de la cuenta. */
class MiExportacionController
{
    public function __construct(private readonly ContextoDeTenantPort $contextos) {}

    public function show(Request $request, GenerarMiFichaPdf $generar): Response
    {
        $userId = (int) $request->user()->getAuthIdentifier();
        $contexto = $this->contextos->deCuenta($userId);

        if ($contexto === null) {
            return $this->error((new CuentaSinFicha)->getMessage(), 'CUENTA_SIN_FICHA', 403);
        }

        try {
            $pdf = $generar->ejecutar($contexto, $userId);

            return RespuestaDePdf::bytes($pdf['contenido'], $pdf['nombre']);
        } catch (CuentaSinFicha|ColaboradorNoEncontrado $e) {
            return $this->error((new CuentaSinFicha)->getMessage(), 'CUENTA_SIN_FICHA', 403);
        } catch (SinPermisoDeExportacion $e) {
            return $this->error($e->getMessage(), 'SIN_PERMISO_EXPORTACION', 403);
        } catch (ExportacionNoDisponible $e) {
            return $this->error($e->getMessage(), 'EXPORTACION_NO_DISPONIBLE', 503);
        }
    }

    private function error(string $mensaje, string $codigo, int $estado): JsonResponse
    {
        return response()->json(['message' => $mensaje, 'code' => $codigo], $estado);
    }
}
