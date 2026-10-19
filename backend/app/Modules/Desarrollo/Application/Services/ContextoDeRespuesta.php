<?php

namespace App\Modules\Desarrollo\Application\Services;

use App\Modules\Desarrollo\Application\Ports\CuentaColaboradorInterface;
use App\Modules\Desarrollo\Application\Ports\FuenteDeActividadesInterface;
use App\Modules\Desarrollo\Application\Ports\RelojInterface;
use App\Modules\Desarrollo\Domain\Exceptions\ActividadNoEncontradaParaReporte;
use App\Modules\Desarrollo\Domain\ValueObjects\AsistenciaParticipante;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoAccion;
use DateTimeImmutable;

/**
 * Datos que hacen falta para decidir si un colaborador puede responder una encuesta o un seguimiento: su cuenta, la
 * actividad (con su estado calculado), su propia asistencia y la fecha de hoy. Siempre del usuario autenticado.
 */
final class ContextoDeRespuesta
{
    public function __construct(
        private readonly CuentaColaboradorInterface $cuentas,
        private readonly FuenteDeActividadesInterface $actividades,
        private readonly RelojInterface $reloj,
    ) {}

    /**
     * @return array{tenant_id: int, colaborador_id: int, actividad: array<string, mixed>, estado: EstadoAccion, asistencia: AsistenciaParticipante, fecha_fin: DateTimeImmutable, hoy: DateTimeImmutable}
     *
     * @throws ActividadNoEncontradaParaReporte
     */
    public function resolver(int $usuarioId, int $actividadId): array
    {
        $cuenta = $this->cuentas->cuentaDe($usuarioId);
        if ($cuenta === null) {
            throw new ActividadNoEncontradaParaReporte;
        }

        $hoy = $this->reloj->hoy($usuarioId);
        $actividad = null;
        foreach ($this->actividades->actividades($cuenta['tenant_id'], $hoy) as $a) {
            if ($a['actividad_id'] === $actividadId) {
                $actividad = $a;
                break;
            }
        }
        if ($actividad === null || $actividad['fecha_fin'] === null) {
            throw new ActividadNoEncontradaParaReporte;
        }

        $registros = $this->actividades->asistencias($cuenta['tenant_id'], $hoy, ['actividad_id' => $actividadId, 'colaborador_id' => $cuenta['colaborador_id']]);

        return [
            'tenant_id' => $cuenta['tenant_id'],
            'colaborador_id' => $cuenta['colaborador_id'],
            'actividad' => $actividad,
            'estado' => EstadoAccion::from($actividad['estado']),
            'asistencia' => AsistenciaParticipante::from($registros[0]['asistencia'] ?? AsistenciaParticipante::Pendiente->value),
            'fecha_fin' => new DateTimeImmutable($actividad['fecha_fin']),
            'hoy' => $hoy,
        ];
    }
}
