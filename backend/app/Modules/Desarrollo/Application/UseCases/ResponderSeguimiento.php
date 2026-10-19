<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Services\ContextoDeRespuesta;
use App\Modules\Desarrollo\Domain\Entities\SeguimientoImpacto;
use App\Modules\Desarrollo\Domain\Repositories\ReportesRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\ReglasDeSeguimiento;

/** El colaborador responde una sola vez cada hito (30, 60 o 90 días) de una actividad en la que estuvo presente. */
final class ResponderSeguimiento
{
    public function __construct(
        private readonly ContextoDeRespuesta $contexto,
        private readonly ReportesRepositoryInterface $reportes,
        private readonly ReglasDeSeguimiento $reglas,
    ) {}

    /**
     * @return array{actividad_id: int, hito: int, aplicacion: int, apoyo: int, comentario: string|null, fecha: string}
     */
    public function ejecutar(int $usuarioId, int $actividadId, int $hito, int $aplicacion, int $apoyo, ?string $comentario): array
    {
        $c = $this->contexto->resolver($usuarioId, $actividadId);
        $yaRespondio = $this->reportes->seguimientoDe($actividadId, $c['colaborador_id'], $hito) !== null;
        $this->reglas->validar($hito, $c['estado'], $c['asistencia'], $c['fecha_fin'], $c['hoy'], $yaRespondio);

        $comentario = $comentario === null || trim($comentario) === '' ? null : trim($comentario);
        $guardado = $this->reportes->registrarSeguimiento(new SeguimientoImpacto(null, $c['tenant_id'], $actividadId, $c['colaborador_id'], $hito, $aplicacion, $apoyo, $comentario));

        return [
            'actividad_id' => $actividadId,
            'hito' => $guardado->hito,
            'aplicacion' => $guardado->aplicacion,
            'apoyo' => $guardado->apoyo,
            'comentario' => $guardado->comentario,
            'fecha' => ($guardado->creadoEn ?? $c['hoy'])->format('Y-m-d'),
        ];
    }
}
