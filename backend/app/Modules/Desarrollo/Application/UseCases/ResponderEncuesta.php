<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Services\ContextoDeRespuesta;
use App\Modules\Desarrollo\Domain\Entities\EncuestaSatisfaccion;
use App\Modules\Desarrollo\Domain\Repositories\ReportesRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\ReglasDeEncuesta;

/** El colaborador responde una sola vez la encuesta de satisfacción de una actividad en la que estuvo presente. */
final class ResponderEncuesta
{
    public function __construct(
        private readonly ContextoDeRespuesta $contexto,
        private readonly ReportesRepositoryInterface $reportes,
        private readonly ReglasDeEncuesta $reglas,
    ) {}

    /**
     * @return array{actividad_id: int, calificacion: int, comentario: string|null, fecha: string}
     */
    public function ejecutar(int $usuarioId, int $actividadId, int $calificacion, ?string $comentario): array
    {
        $c = $this->contexto->resolver($usuarioId, $actividadId);
        $yaRespondio = $this->reportes->encuestaDe($actividadId, $c['colaborador_id']) !== null;
        $this->reglas->validar($c['estado'], $c['asistencia'], $c['fecha_fin'], $c['hoy'], $yaRespondio);

        $comentario = $comentario === null || trim($comentario) === '' ? null : trim($comentario);
        $guardada = $this->reportes->registrarEncuesta(new EncuestaSatisfaccion(null, $c['tenant_id'], $actividadId, $c['colaborador_id'], $calificacion, $comentario));

        return [
            'actividad_id' => $actividadId,
            'calificacion' => $guardada->calificacion,
            'comentario' => $guardada->comentario,
            'fecha' => ($guardada->creadoEn ?? $c['hoy'])->format('Y-m-d'),
        ];
    }
}
