<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\Desarrollo\Domain\Entities\Actividad;
use App\Modules\Desarrollo\Domain\Exceptions\AccionNoElegible;
use App\Modules\Desarrollo\Domain\Exceptions\VersionDesactualizada;
use App\Modules\Desarrollo\Domain\Repositories\ActividadRepositoryInterface;
use App\Modules\Desarrollo\Domain\ValueObjects\AsistenciaParticipante;
use App\Modules\Desarrollo\Domain\ValueObjects\CodigoActividad;
use App\Modules\Desarrollo\Domain\ValueObjects\RegistroDeAsistencia;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\ActividadAsistenciaModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\ActividadHistorialModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\ActividadModel;
use DateTimeImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class EloquentActividadRepository implements ActividadRepositoryInterface
{
    public function crear(Actividad $actividad): Actividad
    {
        for ($intento = 1; ; $intento++) {
            try {
                $id = DB::transaction(function () use ($actividad): int {
                    $numero = (int) ActividadModel::query()->where('tenant_id', $actividad->tenantId)->max('numero') + 1;

                    return (int) ActividadModel::query()->create([
                        'tenant_id' => $actividad->tenantId,
                        'numero' => $numero,
                        'codigo' => CodigoActividad::desde($numero),
                        'plan_accion_id' => $actividad->accionId,
                        'lugar_enlace' => $actividad->lugarEnlace,
                        'departamento_id' => $actividad->departamentoId,
                        'version' => 1,
                        'created_by' => $actividad->creadoPor,
                        'updated_by' => $actividad->actualizadoPor,
                    ])->id;
                });

                return $this->obtener($actividad->tenantId, $id) ?? $actividad;
            } catch (UniqueConstraintViolationException $e) {
                // Dos altas simultáneas de la misma acción: la segunda no es válida. Si solo chocó el número secuencial, reintentar.
                if (ActividadModel::query()->where('plan_accion_id', $actividad->accionId)->exists()) {
                    throw new AccionNoElegible;
                }
                if ($intento >= 3) {
                    throw $e;
                }
            }
        }
    }

    public function actualizar(int $actividadId, string $lugarEnlace, ?int $departamentoId, int $usuarioId): void
    {
        ActividadModel::query()->whereKey($actividadId)->update([
            'lugar_enlace' => $lugarEnlace,
            'departamento_id' => $departamentoId,
            'updated_by' => $usuarioId,
            'updated_at' => now(),
        ]);
    }

    public function obtener(int $tenantId, int $id): ?Actividad
    {
        $m = ActividadModel::query()->where('tenant_id', $tenantId)->whereKey($id)->first();

        return $m === null ? null : $this->aEntidad($m);
    }

    public function obtenerPorAccion(int $tenantId, int $accionId): ?Actividad
    {
        $m = ActividadModel::query()->where('tenant_id', $tenantId)->where('plan_accion_id', $accionId)->first();

        return $m === null ? null : $this->aEntidad($m);
    }

    public function listarDelTenant(int $tenantId): array
    {
        return ActividadModel::query()->where('tenant_id', $tenantId)->orderBy('id')->get()
            ->map(fn (ActividadModel $m) => $this->aEntidad($m))->all();
    }

    public function porAcciones(int $tenantId, array $accionIds): array
    {
        if ($accionIds === []) {
            return [];
        }

        return ActividadModel::query()->where('tenant_id', $tenantId)->whereIn('plan_accion_id', $accionIds)->orderBy('id')->get()
            ->map(fn (ActividadModel $m) => $this->aEntidad($m))->all();
    }

    public function idsDeAccionesConActividad(int $tenantId): array
    {
        return ActividadModel::query()->where('tenant_id', $tenantId)->pluck('plan_accion_id')->map(fn ($i) => (int) $i)->all();
    }

    public function registrosDe(int $actividadId): array
    {
        $registros = [];
        foreach (ActividadAsistenciaModel::query()->where('actividad_id', $actividadId)->orderBy('id')->get() as $m) {
            $registros[(int) $m->colaborador_id] = $this->aRegistro($m);
        }

        return $registros;
    }

    public function registradosPorActividades(array $actividadIds): array
    {
        if ($actividadIds === []) {
            return [];
        }
        $porActividad = [];
        foreach (ActividadAsistenciaModel::query()->whereIn('actividad_id', $actividadIds)->get(['actividad_id', 'colaborador_id']) as $m) {
            $porActividad[(int) $m->actividad_id][] = (int) $m->colaborador_id;
        }

        return $porActividad;
    }

    public function guardarAsistencia(int $actividadId, int $versionEsperada, array $guardar, array $eliminar, array $historial, int $usuarioId): int
    {
        return DB::transaction(function () use ($actividadId, $versionEsperada, $guardar, $eliminar, $historial, $usuarioId): int {
            $filas = ActividadModel::query()->whereKey($actividadId)->where('version', $versionEsperada)->update([
                'version' => $versionEsperada + 1,
                'updated_by' => $usuarioId,
                'updated_at' => now(),
            ]);
            if ($filas === 0) {
                throw new VersionDesactualizada;
            }

            foreach ($guardar as $registro) {
                ActividadAsistenciaModel::query()->updateOrCreate(
                    ['actividad_id' => $actividadId, 'colaborador_id' => $registro->colaboradorId],
                    [
                        'asistencia' => $registro->asistencia->value,
                        'calificacion' => $registro->calificacion,
                        'observaciones' => $registro->observaciones === '' ? null : $registro->observaciones,
                        'registrado_by' => $usuarioId,
                    ],
                );
            }
            if ($eliminar !== []) {
                ActividadAsistenciaModel::query()->where('actividad_id', $actividadId)->whereIn('colaborador_id', $eliminar)->delete();
            }
            foreach ($historial as $cambio) {
                ActividadHistorialModel::query()->create([
                    'actividad_id' => $actividadId,
                    'colaborador_id' => $cambio->colaboradorId,
                    'asistencia_anterior' => $cambio->asistenciaAnterior?->value,
                    'asistencia_nueva' => $cambio->asistenciaNueva?->value,
                    'calificacion_anterior' => $cambio->calificacionAnterior,
                    'calificacion_nueva' => $cambio->calificacionNueva,
                    'user_id' => $usuarioId,
                ]);
            }

            return $versionEsperada + 1;
        });
    }

    public function historial(int $actividadId, int $pagina, int $porPagina): array
    {
        $consulta = ActividadHistorialModel::query()->where('actividad_id', $actividadId);
        $total = (clone $consulta)->count();
        $filas = $consulta->orderByDesc('id')->forPage($pagina, $porPagina)->get();

        return [
            'items' => $filas->map(fn (ActividadHistorialModel $h) => [
                'id' => (int) $h->id,
                'fecha' => $h->created_at === null ? null : new DateTimeImmutable((string) $h->created_at),
                'usuario_id' => (int) $h->user_id,
                'colaborador_id' => (int) $h->colaborador_id,
                'asistencia_anterior' => $h->asistencia_anterior === null ? null : AsistenciaParticipante::from($h->asistencia_anterior),
                'asistencia_nueva' => $h->asistencia_nueva === null ? null : AsistenciaParticipante::from($h->asistencia_nueva),
                'calificacion_anterior' => $h->calificacion_anterior === null ? null : (int) $h->calificacion_anterior,
                'calificacion_nueva' => $h->calificacion_nueva === null ? null : (int) $h->calificacion_nueva,
            ])->all(),
            'total' => $total,
        ];
    }

    public function agregadosPorActividad(array $actividadIds): array
    {
        if ($actividadIds === []) {
            return [];
        }
        $resultado = [];
        $filas = DB::table('actividad_asistencias')->whereIn('actividad_id', $actividadIds)->whereNotNull('calificacion')
            ->groupBy('actividad_id')
            ->selectRaw('actividad_id, count(*) as calificados, sum(calificacion) as suma')->get();
        foreach ($filas as $f) {
            $resultado[(int) $f->actividad_id] = ['calificados' => (int) $f->calificados, 'suma' => (int) $f->suma];
        }

        return $resultado;
    }

    public function agregadosPorColaborador(array $actividadIds): array
    {
        if ($actividadIds === []) {
            return [];
        }
        $resultado = [];
        $filas = DB::table('actividad_asistencias')->whereIn('actividad_id', $actividadIds)->whereNotNull('calificacion')
            ->groupBy('colaborador_id')
            ->selectRaw('colaborador_id, count(*) as calificadas, sum(calificacion) as suma')->get();
        foreach ($filas as $f) {
            $resultado[(int) $f->colaborador_id] = ['calificadas' => (int) $f->calificadas, 'suma' => (int) $f->suma];
        }

        return $resultado;
    }

    public function registrosDeColaborador(array $actividadIds, int $colaboradorId): array
    {
        if ($actividadIds === []) {
            return [];
        }
        $registros = [];
        foreach (ActividadAsistenciaModel::query()->whereIn('actividad_id', $actividadIds)->where('colaborador_id', $colaboradorId)->get() as $m) {
            $registros[(int) $m->actividad_id] = $this->aRegistro($m);
        }

        return $registros;
    }

    public function registrosDeActividades(array $actividadIds): array
    {
        if ($actividadIds === []) {
            return [];
        }

        return ActividadAsistenciaModel::query()->whereIn('actividad_id', $actividadIds)->orderBy('id')->get()
            ->map(fn (ActividadAsistenciaModel $m) => ['actividad_id' => (int) $m->actividad_id, 'registro' => $this->aRegistro($m)])->all();
    }

    public function tieneAsistencia(int $actividadId, int $colaboradorId): bool
    {
        return ActividadAsistenciaModel::query()->where('actividad_id', $actividadId)->where('colaborador_id', $colaboradorId)->exists();
    }

    public function accionesConPresente(array $accionIds, int $colaboradorId): array
    {
        if ($accionIds === []) {
            return [];
        }

        return DB::table('actividad_asistencias as s')
            ->join('actividades as a', 'a.id', '=', 's.actividad_id')
            ->whereIn('a.plan_accion_id', $accionIds)
            ->where('s.colaborador_id', $colaboradorId)
            ->where('s.asistencia', AsistenciaParticipante::Presente->value)
            ->pluck('a.plan_accion_id')->map(fn ($i) => (int) $i)->all();
    }

    private function aEntidad(ActividadModel $m): Actividad
    {
        return new Actividad(
            (int) $m->id,
            (int) $m->tenant_id,
            (int) $m->numero,
            (int) $m->plan_accion_id,
            (string) $m->lugar_enlace,
            $m->departamento_id === null ? null : (int) $m->departamento_id,
            (int) $m->version,
            (int) $m->created_by,
            (int) $m->updated_by,
            $m->created_at === null ? null : new DateTimeImmutable((string) $m->created_at),
            $m->updated_at === null ? null : new DateTimeImmutable((string) $m->updated_at),
        );
    }

    private function aRegistro(ActividadAsistenciaModel $m): RegistroDeAsistencia
    {
        return new RegistroDeAsistencia(
            (int) $m->colaborador_id,
            AsistenciaParticipante::from($m->asistencia),
            $m->calificacion === null ? null : (int) $m->calificacion,
            $m->observaciones,
        );
    }
}
