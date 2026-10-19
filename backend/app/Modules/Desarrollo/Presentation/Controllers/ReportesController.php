<?php

namespace App\Modules\Desarrollo\Presentation\Controllers;

use App\Modules\Desarrollo\Application\Services\ResolvedorDeFiltros;
use App\Modules\Desarrollo\Application\UseCases\ExportarReportes;
use App\Modules\Desarrollo\Application\UseCases\ListarComentariosDeSatisfaccion;
use App\Modules\Desarrollo\Application\UseCases\ListarDetalle;
use App\Modules\Desarrollo\Application\UseCases\ListarSeguimiento;
use App\Modules\Desarrollo\Application\UseCases\ObtenerDashboard;
use App\Modules\Desarrollo\Application\UseCases\ObtenerOpcionesDeReportes;
use App\Modules\Desarrollo\Presentation\Requests\ExportarReportesRequest;
use App\Modules\Desarrollo\Presentation\Requests\FiltrosReportesRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReportesController
{
    use RespondeErroresDeReportes;

    public function opciones(Request $request, ObtenerOpcionesDeReportes $obtener): Response
    {
        return $this->responder(fn () => response()->json(['data' => $obtener->ejecutar($this->tenant($request))]));
    }

    public function dashboard(FiltrosReportesRequest $request, ResolvedorDeFiltros $filtros, ObtenerDashboard $obtener): Response
    {
        return $this->responder(fn () => response()->json([
            'data' => $obtener->ejecutar($this->tenant($request), $this->usuario($request), $filtros->resolver($request->validated(), $this->usuario($request))),
        ]));
    }

    public function detalle(FiltrosReportesRequest $request, ResolvedorDeFiltros $filtros, ListarDetalle $listar): Response
    {
        return $this->responder(function () use ($request, $filtros, $listar): JsonResponse {
            $usuario = $this->usuario($request);
            $r = $listar->ejecutar(
                $this->tenant($request), $usuario, $filtros->resolver($request->validated(), $usuario),
                $request->validated('q'), $request->pagina(), $request->porPagina(),
            );

            return $this->pagina($r['items'], $r['total'], $request->pagina(), $request->porPagina(), ['fuentes_no_disponibles' => $r['fuentes_no_disponibles']]);
        });
    }

    public function satisfaccion(Request $request, int $actividadId, ListarComentariosDeSatisfaccion $listar): Response
    {
        $validado = $request->validate(['page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);

        return $this->responder(function () use ($request, $actividadId, $listar, $validado): JsonResponse {
            $pagina = (int) ($validado['page'] ?? 1);
            $porPagina = (int) ($validado['per_page'] ?? 20);
            $r = $listar->ejecutar($this->tenant($request), $this->usuario($request), $actividadId, $pagina, $porPagina);

            return response()->json([
                'data' => ['promedio' => $r['promedio'], 'respuestas' => $r['respuestas'], 'comentarios' => $r['comentarios']],
                'meta' => ['total' => $r['total'], 'page' => max(1, $pagina), 'per_page' => $porPagina, 'last_page' => max(1, (int) ceil($r['total'] / max(1, $porPagina)))],
            ]);
        });
    }

    public function exportar(ExportarReportesRequest $request, ResolvedorDeFiltros $filtros, ExportarReportes $exportar): Response
    {
        return $this->responder(function () use ($request, $filtros, $exportar): Response {
            $usuario = $this->usuario($request);
            $archivo = $exportar->ejecutar($this->tenant($request), $usuario, $filtros->resolver($request->validated(), $usuario), (string) $request->validated('formato'));

            return response($archivo['contenido'], 200, [
                'Content-Type' => $archivo['tipo'],
                'Content-Disposition' => 'attachment; filename="'.$archivo['nombre'].'"',
                'Cache-Control' => 'no-store',
            ]);
        });
    }

    public function seguimiento(FiltrosReportesRequest $request, ResolvedorDeFiltros $filtros, ListarSeguimiento $listar): Response
    {
        return $this->responder(function () use ($request, $filtros, $listar): JsonResponse {
            $usuario = $this->usuario($request);
            $r = $listar->ejecutar($this->tenant($request), $usuario, $filtros->resolver($request->validated(), $usuario), $request->pagina(), $request->porPagina());

            return $this->pagina($r['items'], $r['total'], $request->pagina(), $request->porPagina(), ['fuentes_no_disponibles' => $r['fuentes_no_disponibles']]);
        });
    }
}
