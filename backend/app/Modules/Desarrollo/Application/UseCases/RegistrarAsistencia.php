<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\AccionesDePlanInterface;
use App\Modules\Desarrollo\Application\Services\ArmadorDeActividades;
use App\Modules\Desarrollo\Domain\Exceptions\ActividadNoEncontrada;
use App\Modules\Desarrollo\Domain\Exceptions\AsistenciaNoRegistrable;
use App\Modules\Desarrollo\Domain\Exceptions\PlanCerradoParaActividad;
use App\Modules\Desarrollo\Domain\Exceptions\RegistroDeAsistenciaInvalido;
use App\Modules\Desarrollo\Domain\Exceptions\VersionDesactualizada;
use App\Modules\Desarrollo\Domain\Repositories\ActividadRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\ReglasDeActividad;
use App\Modules\Desarrollo\Domain\Services\ValidadorDeRegistrosDeAsistencia;
use App\Modules\Desarrollo\Domain\ValueObjects\AsistenciaParticipante;
use App\Modules\Desarrollo\Domain\ValueObjects\CambioDeAsistencia;
use App\Modules\Desarrollo\Domain\ValueObjects\RegistroDeAsistencia;

/**
 * Guarda de una vez, y todo o nada, la asistencia, la calificación y las observaciones de los inscritos. Solo se
 * escriben los registros que cambian; volver a Pendiente elimina la fila (y con ella la calificación y las
 * observaciones). Cada cambio de asistencia o de calificación queda en el historial.
 */
final class RegistrarAsistencia
{
    public function __construct(
        private readonly ActividadRepositoryInterface $actividades,
        private readonly AccionesDePlanInterface $acciones,
        private readonly ReglasDeActividad $reglas,
        private readonly ValidadorDeRegistrosDeAsistencia $validador,
        private readonly ArmadorDeActividades $armador,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $registros
     * @return array<string, mixed>
     *
     * @throws ActividadNoEncontrada
     * @throws PlanCerradoParaActividad
     * @throws AsistenciaNoRegistrable
     * @throws RegistroDeAsistenciaInvalido
     * @throws VersionDesactualizada
     */
    public function ejecutar(int $tenantId, int $usuarioId, int $id, int $version, array $registros): array
    {
        $actividad = $this->actividades->obtener($tenantId, $id) ?? throw new ActividadNoEncontrada;
        $accion = $this->acciones->obtener($tenantId, $actividad->accionId) ?? throw new ActividadNoEncontrada;

        $motivo = $this->reglas->motivoBloqueo($this->reglas->estado($accion, $this->armador->hoy($usuarioId)), $accion->planEstado);
        if ($motivo === 'plan_cerrado') {
            throw new PlanCerradoParaActividad;
        }
        if ($motivo !== null) {
            throw new AsistenciaNoRegistrable;
        }
        if ($version !== $actividad->version) {
            throw new VersionDesactualizada;
        }

        $this->validador->validar($registros, $accion->colaboradorIds);

        $existentes = $this->actividades->registrosDe($id);
        $guardar = [];
        $eliminar = [];
        $historial = [];
        foreach ($registros as $r) {
            $colaboradorId = (int) $r['colaborador_id'];
            $asistencia = AsistenciaParticipante::from($r['asistencia']);
            $actual = $existentes[$colaboradorId] ?? null;

            if ($asistencia === AsistenciaParticipante::Pendiente) {
                if ($actual !== null) {
                    $eliminar[] = $colaboradorId;
                    $historial[] = new CambioDeAsistencia($colaboradorId, $actual->asistencia, null, $actual->calificacion, null);
                }

                continue;
            }

            $observaciones = isset($r['observaciones']) && trim((string) $r['observaciones']) !== '' ? trim((string) $r['observaciones']) : null;
            $nuevo = new RegistroDeAsistencia($colaboradorId, $asistencia, $asistencia === AsistenciaParticipante::Presente ? ($r['calificacion'] ?? null) : null, $observaciones);
            if ($actual !== null && $actual->igualA($nuevo)) {
                continue;
            }
            $guardar[] = $nuevo;
            if ($actual?->asistencia !== $nuevo->asistencia || $actual?->calificacion !== $nuevo->calificacion) {
                $historial[] = new CambioDeAsistencia($colaboradorId, $actual?->asistencia, $nuevo->asistencia, $actual?->calificacion, $nuevo->calificacion);
            }
        }

        if ($guardar !== [] || $eliminar !== []) {
            $this->actividades->guardarAsistencia($id, $version, $guardar, $eliminar, $historial, $usuarioId);
        }

        return $this->armador->detalle($tenantId, $usuarioId, $this->actividades->obtener($tenantId, $id) ?? $actividad, $accion);
    }
}
