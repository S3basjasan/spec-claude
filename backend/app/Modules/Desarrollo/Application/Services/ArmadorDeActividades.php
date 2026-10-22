<?php

namespace App\Modules\Desarrollo\Application\Services;

use App\Modules\Desarrollo\Application\Ports\DepartamentosInterface;
use App\Modules\Desarrollo\Application\Ports\NombresDeReferenciaInterface;
use App\Modules\Desarrollo\Application\Ports\NombresDeUsuarioInterface;
use App\Modules\Desarrollo\Application\Ports\RelojInterface;
use App\Modules\Desarrollo\Domain\Entities\Actividad;
use App\Modules\Desarrollo\Domain\Repositories\ActividadRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\CalculadorDePromedios;
use App\Modules\Desarrollo\Domain\Services\ReglasDeActividad;
use App\Modules\Desarrollo\Domain\ValueObjects\AccionDeActividad;
use App\Modules\Desarrollo\Domain\ValueObjects\AsistenciaParticipante;
use DateTimeImmutable;
use Throwable;

/** Arma las respuestas de actividad (resumen y detalle) a partir de la actividad y de su acción vigente en el plan. */
final class ArmadorDeActividades
{
    public const NO_DISPONIBLE = 'No disponible';

    public function __construct(
        private readonly ReglasDeActividad $reglas,
        private readonly ActividadRepositoryInterface $actividades,
        private readonly DepartamentosInterface $departamentos,
        private readonly NombresDeReferenciaInterface $referencias,
        private readonly NombresDeUsuarioInterface $usuarios,
        private readonly RelojInterface $reloj,
        private readonly CalculadorDePromedios $promedios,
    ) {}

    public function hoy(int $usuarioId): DateTimeImmutable
    {
        return $this->reloj->hoy($usuarioId);
    }

    /**
     * Resumen de varias actividades con una sola consulta de departamentos y de registros.
     *
     * @param  list<Actividad>  $actividades
     * @param  array<int, AccionDeActividad>  $acciones  accion_id => acción
     * @return list<array<string, mixed>>
     */
    public function resumenes(int $usuarioId, array $actividades, array $acciones): array
    {
        $hoy = $this->hoy($usuarioId);
        $departamentos = $this->departamentos->porIds(array_values(array_filter(array_map(fn (Actividad $a) => $a->departamentoId, $actividades))));
        $registrados = $this->actividades->registradosPorActividades(array_values(array_filter(array_map(fn (Actividad $a) => $a->id, $actividades))));

        $filas = [];
        foreach ($actividades as $actividad) {
            $accion = $acciones[$actividad->accionId] ?? null;
            if ($accion === null) {
                continue;
            }
            $filas[] = $this->resumen($actividad, $accion, $hoy, $departamentos, $registrados[(int) $actividad->id] ?? []);
        }

        return $filas;
    }

    /**
     * @param  array<int, string>  $departamentos  id => nombre
     * @param  list<int>  $registrados  colaboradores con registro
     * @return array<string, mixed>
     */
    public function resumen(Actividad $actividad, AccionDeActividad $accion, DateTimeImmutable $hoy, array $departamentos, array $registrados): array
    {
        $estado = $this->reglas->estado($accion, $hoy);
        $registradosInscritos = count(array_intersect($registrados, $accion->colaboradorIds));

        return [
            'id' => (int) $actividad->id,
            'codigo' => $actividad->codigo(),
            'accion_id' => $accion->accionId,
            'nombre' => $accion->nombre,
            'tipo' => $accion->tipo?->value,
            'plan' => ['id' => $accion->planId, 'codigo' => $accion->planCodigo, 'nombre' => $accion->planNombre, 'estado' => $accion->planEstado->value],
            'fecha_inicio' => $accion->fechaInicio?->format('Y-m-d'),
            'fecha_fin' => $accion->fechaFin?->format('Y-m-d'),
            'lugar_enlace' => $actividad->lugarEnlace,
            'formador' => $accion->formador,
            'departamento' => $actividad->departamentoId === null ? null : [
                'id' => $actividad->departamentoId,
                'nombre' => $departamentos[$actividad->departamentoId] ?? self::NO_DISPONIBLE,
            ],
            'capacidad' => $accion->capacidadMaxima,
            'inscritos' => $accion->inscritos(),
            'estado' => $estado->value,
            'asistencia_pendiente' => $this->reglas->asistenciaPendiente($estado, $accion->inscritos(), $registradosInscritos),
        ];
    }

    /**
     * Detalle para el administrador: incluye participantes con observaciones y auditoría.
     *
     * @return array<string, mixed>
     */
    public function detalle(int $tenantId, int $usuarioId, Actividad $actividad, AccionDeActividad $accion): array
    {
        $hoy = $this->hoy($usuarioId);
        $estado = $this->reglas->estado($accion, $hoy);
        $registros = $this->actividades->registrosDe((int) $actividad->id);
        $departamentos = $actividad->departamentoId === null ? [] : $this->departamentos->porIds([$actividad->departamentoId]);
        $resumen = $this->resumen($actividad, $accion, $hoy, $departamentos, array_keys($registros));

        $nombres = $this->nombresDeColaboradores($tenantId, $accion->colaboradorIds);
        $competencias = $this->nombresDeCompetencias($accion->competenciaIds);
        $motivo = $this->reglas->motivoBloqueo($estado, $accion->planEstado);

        $participantes = [];
        $conteo = ['presentes' => 0, 'ausentes' => 0, 'justificados' => 0, 'pendientes' => 0];
        $calificaciones = [];
        foreach ($accion->colaboradorIds as $colaboradorId) {
            $registro = $registros[$colaboradorId] ?? null;
            $asistencia = $registro?->asistencia ?? AsistenciaParticipante::Pendiente;
            $conteo[match ($asistencia) {
                AsistenciaParticipante::Presente => 'presentes',
                AsistenciaParticipante::Ausente => 'ausentes',
                AsistenciaParticipante::Justificado => 'justificados',
                AsistenciaParticipante::Pendiente => 'pendientes',
            }]++;
            if ($registro?->calificacion !== null) {
                $calificaciones[] = $registro->calificacion;
            }
            $participantes[] = [
                'colaborador_id' => $colaboradorId,
                'nombre' => $nombres[$colaboradorId] ?? self::NO_DISPONIBLE,
                'asistencia' => $asistencia->value,
                'calificacion' => $registro?->calificacion,
                'observaciones' => $registro?->observaciones,
            ];
        }

        $usuariosAuditoria = $this->nombresDeUsuarios([$actividad->creadoPor, $actividad->actualizadoPor]);

        return $resumen + [
            'competencias' => array_map(fn (int $id) => ['id' => $id, 'nombre' => $competencias[$id] ?? self::NO_DISPONIBLE], $accion->competenciaIds),
            'horas' => $accion->duracionHoras,
            'version' => $actividad->version,
            'puede_editar' => $this->reglas->puedeEditar($accion->planEstado),
            'puede_registrar_asistencia' => $motivo === null,
            'motivo_bloqueo' => $motivo,
            'resumen_asistencia' => $conteo + ['calificados' => count($calificaciones), 'promedio' => $this->promedios->promedioDe($calificaciones)],
            'participantes' => $participantes,
            'auditoria' => [
                'creado_por' => $usuariosAuditoria[$actividad->creadoPor] ?? self::NO_DISPONIBLE,
                'creado_en' => $actividad->creadoEn?->format('c'),
                'modificado_por' => $usuariosAuditoria[$actividad->actualizadoPor] ?? self::NO_DISPONIBLE,
                'modificado_en' => $actividad->actualizadoEn?->format('c'),
            ],
        ];
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    public function nombresDeColaboradores(int $tenantId, array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        try {
            return $this->referencias->colaboradores($tenantId, $ids);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    public function nombresDeCompetencias(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        try {
            return array_map(fn (array $c) => $c['nombre'], $this->referencias->competencias($ids));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    public function nombresDeUsuarios(array $ids): array
    {
        try {
            return $this->usuarios->nombresDe(array_values(array_unique($ids)));
        } catch (Throwable) {
            return [];
        }
    }
}
