<?php

namespace App\Modules\GestionPersonal\Presentation\Controllers;

use App\Modules\GestionPersonal\Application\Ports\ContextoDeTenantPort;
use App\Modules\GestionPersonal\Application\Services\AutorizadorDeExportacion;
use App\Modules\GestionPersonal\Application\UseCases\GenerarFichaPdf;
use App\Modules\GestionPersonal\Domain\Exceptions\ColaboradorNoEncontrado;
use App\Modules\GestionPersonal\Domain\Exceptions\ExportacionNoDisponible;
use App\Modules\GestionPersonal\Presentation\Support\RespuestaDePdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Exportación administrativa de una ficha del tenant. Los errores se responden como JSON antes de enviar bytes. */
class ExportacionFichaController
{
    public function __construct(private readonly ContextoDeTenantPort $contextos) {}

    public function show(Request $request, int $colaborador, GenerarFichaPdf $generar, AutorizadorDeExportacion $autorizador): Response
    {
        $contexto = $this->contextos->deCuenta((int) $request->user()->getAuthIdentifier());

        if ($contexto === null) {
            return $this->error('Colaborador no encontrado.', 'COLABORADOR_NO_ENCONTRADO', 404);
        }

        try {
            $pdf = $generar->ejecutar($contexto, $colaborador, function () use ($request, $contexto, $colaborador, $autorizador): void {
                $actual = $this->contextos->deCuenta((int) $request->user()->getAuthIdentifier());
                if ($actual === null || $actual->tenantId !== $contexto->tenantId) {
                    throw new ColaboradorNoEncontrado;
                }
                $autorizador->fichaDelTenant($actual, $colaborador);
            });

            return RespuestaDePdf::bytes($pdf['contenido'], $pdf['nombre']);
        } catch (ColaboradorNoEncontrado $e) {
            return $this->error($e->getMessage(), 'COLABORADOR_NO_ENCONTRADO', 404);
        } catch (ExportacionNoDisponible $e) {
            return $this->error($e->getMessage(), 'EXPORTACION_NO_DISPONIBLE', 503);
        }
    }

    private function error(string $mensaje, string $codigo, int $estado): JsonResponse
    {
        return response()->json(['message' => $mensaje, 'code' => $codigo], $estado);
    }
}
