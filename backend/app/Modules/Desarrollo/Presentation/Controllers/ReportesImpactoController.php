<?php

namespace App\Modules\Desarrollo\Presentation\Controllers;

use App\Modules\Desarrollo\Application\UseCases\ListarImpactos;
use App\Modules\Desarrollo\Application\UseCases\RegistrarImpacto;
use App\Modules\Desarrollo\Presentation\Requests\GuardarImpactoRequest;
use App\Modules\Desarrollo\Presentation\Requests\ListarImpactosRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ReportesImpactoController
{
    use RespondeErroresDeReportes;

    public function index(ListarImpactosRequest $request, ListarImpactos $listar): Response
    {
        return $this->responder(function () use ($request, $listar): JsonResponse {
            $v = $request->validated();
            $pagina = (int) ($v['page'] ?? 1);
            $porPagina = (int) ($v['per_page'] ?? 20);
            $r = $listar->ejecutar($this->tenant($request), $this->usuario($request), isset($v['plan_id']) ? (int) $v['plan_id'] : null, $v['evaluado'] ?? null, $pagina, $porPagina);

            return $this->pagina($r['items'], $r['total'], $pagina, $porPagina);
        });
    }

    public function update(GuardarImpactoRequest $request, int $planAccionId, RegistrarImpacto $registrar): Response
    {
        return $this->responder(fn () => response()->json([
            'data' => $registrar->ejecutar($this->tenant($request), $this->usuario($request), $planAccionId, $request->validated()),
        ]));
    }
}
