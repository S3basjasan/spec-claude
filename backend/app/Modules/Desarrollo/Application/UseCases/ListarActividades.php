<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\AccionesDePlanInterface;
use App\Modules\Desarrollo\Application\Services\ArmadorDeActividades;
use App\Modules\Desarrollo\Domain\Entities\Actividad;
use App\Modules\Desarrollo\Domain\Repositories\ActividadRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\ReglasDeActividad;
use App\Modules\Desarrollo\Domain\ValueObjects\AccionDeActividad;
use App\Modules\Desarrollo\Domain\ValueObjects\FiltroActividades;

/**
 * Lista las actividades del tenant. El nombre, el tipo, las fechas, el estado, la competencia y el plan son datos de la
 * acción en el plan vigente, así que los filtros se aplican tras leerlas por lote y antes de paginar. Sin fechas se
 * aplica el año actual (superposición con las fechas de la acción).
 */
final class ListarActividades
{
    public function __construct(
        private readonly ActividadRepositoryInterface $actividades,
        private readonly AccionesDePlanInterface $acciones,
        private readonly ReglasDeActividad $reglas,
        private readonly ArmadorDeActividades $armador,
    ) {}

    /**
     * @return array{data: list<array<string, mixed>>, meta: array{page: int, per_page: int, total: int, last_page: int}}
     */
    public function ejecutar(int $tenantId, int $usuarioId, FiltroActividades $filtro): array
    {
        $todas = $this->actividades->listarDelTenant($tenantId);
        $acciones = $this->acciones->porIds($tenantId, array_map(fn (Actividad $a) => $a->accionId, $todas));
        $hoy = $this->armador->hoy($usuarioId);

        $desde = $filtro->fechaDesde;
        $hasta = $filtro->fechaHasta;
        if ($desde === null && $hasta === null) {
            $anio = $hoy->format('Y');
            $desde = "{$anio}-01-01";
            $hasta = "{$anio}-12-31";
        }
        $q = $filtro->q === null ? null : mb_strtolower(trim($filtro->q));

        $filtradas = array_values(array_filter($todas, function (Actividad $a) use ($acciones, $filtro, $hoy, $desde, $hasta, $q): bool {
            $accion = $acciones[$a->accionId] ?? null;
            if ($accion === null) {
                return false;
            }
            if ($q !== null && $q !== '' && ! str_contains(mb_strtolower($accion->nombre), $q)) {
                return false;
            }
            if ($filtro->tipo !== null && $accion->tipo !== $filtro->tipo) {
                return false;
            }
            if ($filtro->estado !== null && $this->reglas->estado($accion, $hoy) !== $filtro->estado) {
                return false;
            }
            if ($filtro->departamentoId !== null && $a->departamentoId !== $filtro->departamentoId) {
                return false;
            }
            if ($filtro->competenciaId !== null && ! in_array($filtro->competenciaId, $accion->competenciaIds, true)) {
                return false;
            }
            if ($filtro->planId !== null && $accion->planId !== $filtro->planId) {
                return false;
            }

            return $this->seSuperpone($accion, $desde, $hasta);
        }));

        usort($filtradas, function (Actividad $x, Actividad $y) use ($acciones): int {
            $ix = $acciones[$x->accionId]->fechaInicio?->format('Y-m-d') ?? '';
            $iy = $acciones[$y->accionId]->fechaInicio?->format('Y-m-d') ?? '';

            return [$iy, $x->numero] <=> [$ix, $y->numero];
        });

        $total = count($filtradas);
        $pagina = array_slice($filtradas, ($filtro->pagina - 1) * $filtro->porPagina, $filtro->porPagina);

        return [
            'data' => $this->armador->resumenes($usuarioId, $pagina, $acciones),
            'meta' => ['page' => $filtro->pagina, 'per_page' => $filtro->porPagina, 'total' => $total, 'last_page' => max(1, (int) ceil($total / $filtro->porPagina))],
        ];
    }

    private function seSuperpone(AccionDeActividad $accion, ?string $desde, ?string $hasta): bool
    {
        $inicio = $accion->fechaInicio?->format('Y-m-d');
        $fin = $accion->fechaFin?->format('Y-m-d');
        if ($desde !== null && $fin !== null && $fin < $desde) {
            return false;
        }

        return ! ($hasta !== null && $inicio !== null && $inicio > $hasta);
    }
}
