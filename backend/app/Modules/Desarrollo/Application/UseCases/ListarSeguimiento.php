<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Services\RecolectorDeReportes;
use App\Modules\Desarrollo\Domain\Repositories\ReportesRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\CalculadorDeIndicadores;
use App\Modules\Desarrollo\Domain\Services\CalculadorDeSeguimiento;
use App\Modules\Desarrollo\Domain\ValueObjects\FiltroReportes;
use DateTimeImmutable;

/** Impacto a 30, 60 y 90 días: por actividad Finalizada del período, tasa de respuesta, promedios y alerta de reingreso. */
final class ListarSeguimiento
{
    public function __construct(
        private readonly RecolectorDeReportes $recolector,
        private readonly CalculadorDeIndicadores $indicadores,
        private readonly CalculadorDeSeguimiento $seguimiento,
        private readonly ReportesRepositoryInterface $reportes,
    ) {}

    /**
     * @return array{items: list<array<string, mixed>>, total: int, fuentes_no_disponibles: list<string>}
     */
    public function ejecutar(int $tenantId, int $usuarioId, FiltroReportes $filtro, int $pagina, int $porPagina): array
    {
        $filas = $this->todas($tenantId, $usuarioId, $filtro, $fuentes);
        $pagina = max(1, $pagina);

        return ['items' => array_slice($filas, ($pagina - 1) * $porPagina, $porPagina), 'total' => count($filas), 'fuentes_no_disponibles' => $fuentes];
    }

    /**
     * Todas las filas sin paginar (también las usa la exportación).
     *
     * @return list<array<string, mixed>>
     */
    public function todasLasFilas(int $tenantId, int $usuarioId, FiltroReportes $filtro): array
    {
        return $this->todas($tenantId, $usuarioId, $filtro, $fuentes);
    }

    /**
     * @param  list<string>|null  $fuentes
     * @return list<array<string, mixed>>
     */
    private function todas(int $tenantId, int $usuarioId, FiltroReportes $filtro, ?array &$fuentes): array
    {
        $datos = $this->recolector->recolectar($tenantId, $usuarioId);
        $fuentes = $datos->noDisponibles;
        $actividades = array_values(array_filter($this->indicadores->filas($datos, $filtro), fn (array $f) => $f['estado'] === 'Finalizada'));
        if ($actividades === []) {
            return [];
        }

        $porActividad = [];
        foreach ($this->reportes->seguimientosDe($tenantId, array_column($actividades, 'actividad_id')) as $s) {
            $porActividad[$s->actividadId][] = $s;
        }

        $filas = [];
        foreach ($actividades as $a) {
            $elegibles = $a['presentes'];
            $respuestas = [];
            foreach ($porActividad[$a['actividad_id']] ?? [] as $s) {
                if (in_array($s->colaboradorId, $elegibles, true)) {
                    $respuestas[] = ['hito' => $s->hito, 'aplicacion' => $s->aplicacion, 'apoyo' => $s->apoyo];
                }
            }
            $r = $this->seguimiento->calcular(count($elegibles), $respuestas, new DateTimeImmutable($a['fecha_fin']));

            $filas[] = [
                'actividad_id' => $a['actividad_id'],
                'codigo' => $a['codigo'],
                'nombre' => $a['nombre'],
                'fecha_fin' => $a['fecha_fin'],
                'elegibles' => count($elegibles),
                'hitos' => array_map(fn (array $h) => [
                    'hito' => $h['hito'],
                    'vencimiento' => $h['vencimiento'],
                    'respuestas' => $h['respuestas'],
                    'tasa_respuesta' => $h['tasa_respuesta'] === null ? null : round($h['tasa_respuesta'], 1),
                    'promedio_aplicacion' => $h['promedio_aplicacion'] === null ? null : round($h['promedio_aplicacion'], 1),
                    'promedio_apoyo' => $h['promedio_apoyo'] === null ? null : round($h['promedio_apoyo'], 1),
                ], $r['hitos']),
                'alerta_reingreso' => $r['alerta_reingreso'],
            ];
        }

        return $filas;
    }
}
