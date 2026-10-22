<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\DepartamentosInterface;
use App\Modules\Desarrollo\Application\Services\RecolectorDeReportes;
use App\Modules\Desarrollo\Domain\Services\CalculadorDeIndicadores;
use App\Modules\Desarrollo\Domain\Services\CalculadorDePeriodo;
use App\Modules\Desarrollo\Domain\ValueObjects\FiltroReportes;
use Throwable;

/**
 * Dashboard de indicadores del período comparado con el período anterior equivalente (mismos filtros), con los
 * indicadores de gestión y los datos de los tres gráficos. Las métricas se calculan bajo demanda; no se guardan.
 */
final class ObtenerDashboard
{
    public function __construct(
        private readonly RecolectorDeReportes $recolector,
        private readonly CalculadorDeIndicadores $indicadores,
        private readonly CalculadorDePeriodo $periodos,
        private readonly DepartamentosInterface $departamentos,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function ejecutar(int $tenantId, int $usuarioId, FiltroReportes $filtro): array
    {
        $datos = $this->recolector->recolectar($tenantId, $usuarioId);
        $anteriorPeriodo = $filtro->periodo->anterior();
        $actual = $this->indicadores->calcular($datos, $filtro);
        $anterior = $this->indicadores->calcular($datos, $filtro->conPeriodo($anteriorPeriodo));

        $armar = fn (string $bloque, string $clave, array $extras = []) => $this->indicador($actual[$bloque][$clave], $anterior[$bloque][$clave], $extras);

        return [
            'periodo' => $filtro->periodo->aArreglo(),
            'periodo_anterior' => $anteriorPeriodo->aArreglo(),
            'fuentes_no_disponibles' => $datos->noDisponibles,
            'indicadores' => [
                'plan_cumplido' => $armar('indicadores', 'plan_cumplido', ['acciones_finalizadas', 'acciones_validas']),
                'total_horas' => $armar('indicadores', 'total_horas', ['horas_actividades', 'horas_microlearning']),
                'satisfaccion' => $armar('indicadores', 'satisfaccion', ['respuestas']),
                'brechas_cerradas' => $armar('indicadores', 'brechas_cerradas', ['detectadas']),
                'roi_estimado' => $armar('indicadores', 'roi_estimado', ['acciones_evaluadas']),
            ],
            'gestion' => [
                'inversion_por_colaborador' => $armar('gestion', 'inversion_por_colaborador'),
                'horas_por_colaborador' => $armar('gestion', 'horas_por_colaborador'),
                'costo_por_hora' => $armar('gestion', 'costo_por_hora'),
            ],
            'graficos' => [
                'horas_por_departamento' => $this->horasPorDepartamento($actual['graficos']['horas_por_departamento']),
                'evolucion_brechas' => $actual['graficos']['evolucion_brechas'],
                'distribucion_por_tipo' => $this->redondearHoras($actual['graficos']['distribucion_por_tipo']),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $actual
     * @param  array<string, mixed>  $anterior
     * @param  list<string>  $extras  campos adicionales del indicador del período actual
     * @return array<string, mixed>
     */
    private function indicador(array $actual, array $anterior, array $extras): array
    {
        $valorAnterior = $anterior['disponible'] ? $anterior['valor'] : null;
        $v = $this->periodos->variacion($actual['valor'], $valorAnterior);
        $fila = [
            'disponible' => $actual['disponible'],
            'valor' => $this->redondear($v->valor),
            'anterior' => $this->redondear($v->anterior),
            'diferencia' => $this->redondear($v->diferencia),
            'variacion_pct' => $v->variacionPct === null ? null : round($v->variacionPct, 1),
        ];
        foreach ($extras as $campo) {
            $fila[$campo] = is_float($actual[$campo]) ? round($actual[$campo], 2) : $actual[$campo];
        }

        return $fila;
    }

    /**
     * @param  array{disponible: bool, datos: list<array<string, mixed>>}  $grafico
     * @return array{disponible: bool, datos: list<array<string, mixed>>}
     */
    private function horasPorDepartamento(array $grafico): array
    {
        $ids = array_values(array_filter(array_column($grafico['datos'], 'departamento_id'), fn ($i) => $i !== null));
        try {
            $nombres = $this->departamentos->porIds($ids);
        } catch (Throwable) {
            $nombres = [];
        }

        $grafico['datos'] = array_map(fn (array $f) => [
            'departamento_id' => $f['departamento_id'],
            'departamento' => $f['departamento_id'] === null ? 'Sin departamento' : ($nombres[$f['departamento_id']] ?? 'Departamento '.$f['departamento_id']),
            'horas_actividades' => round($f['horas_actividades'], 2),
            'horas_microlearning' => round($f['horas_microlearning'], 2),
        ], $grafico['datos']);

        return $grafico;
    }

    /**
     * @param  array{disponible: bool, datos: list<array<string, mixed>>}  $grafico
     * @return array{disponible: bool, datos: list<array<string, mixed>>}
     */
    private function redondearHoras(array $grafico): array
    {
        $grafico['datos'] = array_map(fn (array $f) => ['tipo' => $f['tipo'], 'horas' => round($f['horas'], 2)], $grafico['datos']);

        return $grafico;
    }

    private function redondear(?float $valor): ?float
    {
        return $valor === null ? null : round($valor, 2);
    }
}
