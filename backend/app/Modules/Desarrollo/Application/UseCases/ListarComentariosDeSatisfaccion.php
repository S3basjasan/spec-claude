<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\FuenteDeActividadesInterface;
use App\Modules\Desarrollo\Application\Ports\RelojInterface;
use App\Modules\Desarrollo\Domain\Exceptions\ActividadNoEncontradaParaReporte;
use App\Modules\Desarrollo\Domain\Repositories\ReportesRepositoryInterface;

/** Promedio, cantidad de respuestas y comentarios de satisfacción de una actividad, sin ningún dato que identifique al autor. */
final class ListarComentariosDeSatisfaccion
{
    public function __construct(
        private readonly FuenteDeActividadesInterface $actividades,
        private readonly ReportesRepositoryInterface $reportes,
        private readonly RelojInterface $reloj,
    ) {}

    /**
     * @return array{promedio: float|null, respuestas: int, comentarios: list<array{calificacion: int, comentario: string, fecha: string}>, total: int}
     */
    public function ejecutar(int $tenantId, int $usuarioId, int $actividadId, int $pagina, int $porPagina): array
    {
        $existe = array_filter($this->actividades->actividades($tenantId, $this->reloj->hoy($usuarioId)), fn (array $a) => $a['actividad_id'] === $actividadId);
        if ($existe === []) {
            throw new ActividadNoEncontradaParaReporte;
        }

        $calificaciones = array_map(fn ($e) => $e->calificacion, $this->reportes->encuestasDe($tenantId, [$actividadId]));
        $comentarios = $this->reportes->comentarios($tenantId, $actividadId, max(1, $pagina), $porPagina);

        return [
            'promedio' => $calificaciones === [] ? null : round(array_sum($calificaciones) / count($calificaciones), 1),
            'respuestas' => count($calificaciones),
            'comentarios' => $comentarios['items'],
            'total' => $comentarios['total'],
        ];
    }
}
