<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\DepartamentosInterface;
use App\Modules\Desarrollo\Application\Services\RecolectorDeReportes;
use App\Modules\Desarrollo\Domain\Services\CalculadorDeIndicadores;
use App\Modules\Desarrollo\Domain\ValueObjects\DatosDeReportes;
use App\Modules\Desarrollo\Domain\ValueObjects\FiltroReportes;
use Throwable;

/** Tabla detallada: una fila por actividad En curso o Finalizada del período, con métricas prorrateadas por departamento. */
final class ListarDetalle
{
    public function __construct(
        private readonly RecolectorDeReportes $recolector,
        private readonly CalculadorDeIndicadores $indicadores,
        private readonly DepartamentosInterface $departamentos,
    ) {}

    /**
     * @return array{items: list<array<string, mixed>>, total: int, fuentes_no_disponibles: list<string>}
     */
    public function ejecutar(int $tenantId, int $usuarioId, FiltroReportes $filtro, ?string $q, int $pagina, int $porPagina): array
    {
        $datos = $this->recolector->recolectar($tenantId, $usuarioId);
        $filas = $this->todas($datos, $filtro);

        $texto = $q === null ? '' : mb_strtolower(trim($q));
        if ($texto !== '') {
            $filas = array_values(array_filter($filas, fn (array $f) => str_contains(mb_strtolower($f['nombre'].' '.$f['codigo']), $texto)));
        }

        $total = count($filas);
        $pagina = max(1, $pagina);
        $items = array_slice($filas, ($pagina - 1) * $porPagina, $porPagina);

        return ['items' => $this->conNombres($items), 'total' => $total, 'fuentes_no_disponibles' => $datos->noDisponibles];
    }

    /**
     * Todas las filas del período sin paginar (también las usa la exportación).
     *
     * @return list<array<string, mixed>>
     */
    public function todasLasFilas(int $tenantId, int $usuarioId, FiltroReportes $filtro): array
    {
        return $this->conNombres($this->todas($this->recolector->recolectar($tenantId, $usuarioId), $filtro));
    }

    /** @return list<array<string, mixed>> */
    private function todas(DatosDeReportes $datos, FiltroReportes $filtro): array
    {
        return $this->indicadores->filas($datos, $filtro);
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    private function conNombres(array $filas): array
    {
        $ids = array_values(array_unique(array_filter(array_column($filas, 'departamento_id'), fn ($i) => $i !== null)));
        try {
            $nombres = $ids === [] ? [] : $this->departamentos->porIds($ids);
        } catch (Throwable) {
            $nombres = [];
        }

        return array_map(fn (array $f) => [
            'actividad_id' => $f['actividad_id'],
            'codigo' => $f['codigo'],
            'nombre' => $f['nombre'],
            'tipo' => $f['tipo'],
            'estado' => $f['estado'],
            'fecha_inicio' => $f['fecha_inicio'],
            'fecha_fin' => $f['fecha_fin'],
            'departamento_id' => $f['departamento_id'],
            'departamento' => $f['departamento_id'] === null ? null : ($nombres[$f['departamento_id']] ?? null),
            'asistentes' => $f['asistentes'],
            'inscritos' => $f['inscritos'],
            'horas_persona' => round($f['horas_persona'], 2),
            'costo' => round($f['costo'], 2),
            'calificacion_promedio' => $f['calificacion_promedio'],
            'satisfaccion_promedio' => $f['satisfaccion_promedio'],
            'respuestas_satisfaccion' => $f['respuestas_satisfaccion'],
        ], $filas);
    }
}
