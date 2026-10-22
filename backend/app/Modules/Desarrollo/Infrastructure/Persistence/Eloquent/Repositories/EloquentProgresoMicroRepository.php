<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\Desarrollo\Domain\Entities\IntentoQuiz;
use App\Modules\Desarrollo\Domain\Entities\ProgresoMicro;
use App\Modules\Desarrollo\Domain\Repositories\ProgresoMicroRepositoryInterface;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoIntento;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoProgresoMicro;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicroIntentoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicroProgresoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicroRespuestaModel;
use Illuminate\Support\Facades\DB;

final class EloquentProgresoMicroRepository implements ProgresoMicroRepositoryInterface
{
    public function progresoDe(int $microcontenidoId, int $colaboradorId): ?ProgresoMicro
    {
        $fila = MicroProgresoModel::query()
            ->where('microcontenido_id', $microcontenidoId)->where('colaborador_id', $colaboradorId)->first();

        return $fila === null ? null : $this->progreso($fila);
    }

    public function iniciarOGuardarAvance(int $microcontenidoId, int $colaboradorId, int $tenantId, ?int $posicionSegundos): ProgresoMicro
    {
        return DB::transaction(function () use ($microcontenidoId, $colaboradorId, $tenantId, $posicionSegundos) {
            $fila = MicroProgresoModel::query()
                ->where('microcontenido_id', $microcontenidoId)->where('colaborador_id', $colaboradorId)->first();

            if ($fila === null) {
                $fila = MicroProgresoModel::query()->create([
                    'microcontenido_id' => $microcontenidoId,
                    'colaborador_id' => $colaboradorId,
                    'tenant_id' => $tenantId,
                    'estado' => EstadoProgresoMicro::EnProgreso->value,
                    'posicion_segundos' => $posicionSegundos,
                    'fecha_inicio' => now(),
                ]);
            } elseif ($posicionSegundos !== null && $fila->estado === EstadoProgresoMicro::EnProgreso->value) {
                $fila->forceFill(['posicion_segundos' => $posicionSegundos])->save();
            }

            return $this->progreso($fila);
        });
    }

    public function completar(int $progresoId): ProgresoMicro
    {
        $fila = MicroProgresoModel::query()->findOrFail($progresoId);

        if ($fila->estado !== EstadoProgresoMicro::Completado->value) {
            $fila->forceFill(['estado' => EstadoProgresoMicro::Completado->value, 'fecha_completado' => now()])->save();
        }

        return $this->progreso($fila);
    }

    public function intentoEnCurso(int $progresoId): ?IntentoQuiz
    {
        $fila = MicroIntentoModel::query()->where('progreso_id', $progresoId)->where('estado', EstadoIntento::EnCurso->value)->orderByDesc('id')->first();

        return $fila === null ? null : $this->intento($fila);
    }

    public function obtenerIntento(int $intentoId): ?IntentoQuiz
    {
        $fila = MicroIntentoModel::query()->find($intentoId);

        return $fila === null ? null : $this->intento($fila);
    }

    public function crearIntento(int $progresoId, int $totalPreguntas): IntentoQuiz
    {
        $fila = MicroIntentoModel::query()->create([
            'progreso_id' => $progresoId,
            'estado' => EstadoIntento::EnCurso->value,
            'total_preguntas' => $totalPreguntas,
        ]);

        return $this->intento($fila);
    }

    public function descartarIntentosEnCurso(int $progresoId): void
    {
        DB::transaction(function () use ($progresoId) {
            $ids = MicroIntentoModel::query()->where('progreso_id', $progresoId)->where('estado', EstadoIntento::EnCurso->value)->pluck('id');
            MicroRespuestaModel::query()->whereIn('intento_id', $ids)->delete();
            MicroIntentoModel::query()->whereIn('id', $ids)->delete();
        });
    }

    public function registrarRespuesta(int $intentoId, int $preguntaId, int $opcionId, bool $esCorrecta): void
    {
        MicroRespuestaModel::query()->create([
            'intento_id' => $intentoId,
            'pregunta_id' => $preguntaId,
            'opcion_id' => $opcionId,
            'es_correcta' => $esCorrecta,
        ]);
    }

    public function finalizarIntento(int $intentoId, int $puntaje): void
    {
        MicroIntentoModel::query()->whereKey($intentoId)->update([
            'estado' => EstadoIntento::Finalizado->value,
            'puntaje' => $puntaje,
            'finalizado_en' => now(),
        ]);
    }

    public function ultimoPuntaje(int $progresoId): ?array
    {
        $fila = MicroIntentoModel::query()->where('progreso_id', $progresoId)->where('estado', EstadoIntento::Finalizado->value)->orderByDesc('id')->first();

        return $fila === null ? null : ['correctas' => (int) $fila->puntaje, 'total' => (int) $fila->total_preguntas];
    }

    public function progresosDe(int $colaboradorId): array
    {
        $filas = MicroProgresoModel::query()->where('colaborador_id', $colaboradorId)->orderBy('id')->get();
        if ($filas->isEmpty()) {
            return [];
        }

        $ultimos = [];
        foreach (MicroIntentoModel::query()->whereIn('progreso_id', $filas->pluck('id'))->where('estado', EstadoIntento::Finalizado->value)->orderBy('id')->get() as $intento) {
            $ultimos[(int) $intento->progreso_id] = ['correctas' => (int) $intento->puntaje, 'total' => (int) $intento->total_preguntas];
        }

        return $filas->map(fn (MicroProgresoModel $f) => [
            'progreso' => $this->progreso($f),
            'ultimo_puntaje' => $ultimos[(int) $f->id] ?? null,
        ])->all();
    }

    public function resumenDe(int $tenantId, int $colaboradorId): array
    {
        $base = fn () => MicroProgresoModel::query()->where('tenant_id', $tenantId)->where('colaborador_id', $colaboradorId);

        return [
            'completados' => $base()->where('estado', EstadoProgresoMicro::Completado->value)->count(),
            'en_progreso' => $base()->where('estado', EstadoProgresoMicro::EnProgreso->value)->count(),
            'minutos' => (int) $base()->where('microcontenido_progresos.estado', EstadoProgresoMicro::Completado->value)
                ->join('microcontenidos', 'microcontenidos.id', '=', 'microcontenido_progresos.microcontenido_id')
                ->sum('microcontenidos.duracion_minutos'),
        ];
    }

    public function completaciones(int $tenantId, array $filtros): array
    {
        $consulta = MicroProgresoModel::query()
            ->join('microcontenidos', 'microcontenidos.id', '=', 'microcontenido_progresos.microcontenido_id')
            ->where('microcontenido_progresos.tenant_id', $tenantId)
            ->where('microcontenido_progresos.estado', EstadoProgresoMicro::Completado->value);

        if (isset($filtros['colaborador_id'])) {
            $consulta->where('microcontenido_progresos.colaborador_id', $filtros['colaborador_id']);
        }
        if (isset($filtros['microcontenido_id'])) {
            $consulta->where('microcontenido_progresos.microcontenido_id', $filtros['microcontenido_id']);
        }
        if (isset($filtros['desde'])) {
            $consulta->where('microcontenido_progresos.fecha_completado', '>=', $filtros['desde'].' 00:00:00');
        }
        if (isset($filtros['hasta'])) {
            $consulta->where('microcontenido_progresos.fecha_completado', '<=', $filtros['hasta'].' 23:59:59');
        }

        return $consulta->orderBy('microcontenido_progresos.id')->get([
            'microcontenido_progresos.microcontenido_id as microcontenido_id',
            'microcontenidos.codigo as codigo',
            'microcontenidos.titulo as titulo',
            'microcontenidos.tipo as tipo',
            'microcontenidos.duracion_minutos as duracion_minutos',
            'microcontenido_progresos.colaborador_id as colaborador_id',
            'microcontenido_progresos.fecha_inicio as fecha_inicio',
            'microcontenido_progresos.fecha_completado as fecha_completado',
        ])->map(fn ($f) => [
            'microcontenido_id' => (int) $f->microcontenido_id,
            'codigo' => (string) $f->codigo,
            'titulo' => (string) $f->titulo,
            'tipo' => (string) $f->tipo,
            'duracion_minutos' => (int) $f->duracion_minutos,
            'colaborador_id' => (int) $f->colaborador_id,
            'fecha_inicio' => (string) $f->fecha_inicio,
            'fecha_completado' => (string) $f->fecha_completado,
        ])->all();
    }

    private function progreso(MicroProgresoModel $f): ProgresoMicro
    {
        return new ProgresoMicro(
            (int) $f->id,
            (int) $f->microcontenido_id,
            (int) $f->colaborador_id,
            (int) $f->tenant_id,
            EstadoProgresoMicro::from((string) $f->estado),
            $f->posicion_segundos === null ? null : (int) $f->posicion_segundos,
            $f->fecha_inicio->toIso8601String(),
            $f->fecha_completado?->toIso8601String(),
        );
    }

    private function intento(MicroIntentoModel $f): IntentoQuiz
    {
        $progreso = MicroProgresoModel::query()->findOrFail($f->progreso_id);

        return new IntentoQuiz(
            (int) $f->id,
            (int) $f->progreso_id,
            (int) $progreso->microcontenido_id,
            (int) $progreso->colaborador_id,
            EstadoIntento::from((string) $f->estado),
            $f->puntaje === null ? null : (int) $f->puntaje,
            (int) $f->total_preguntas,
            MicroRespuestaModel::query()->where('intento_id', $f->id)->orderBy('id')->get()
                ->map(fn (MicroRespuestaModel $r) => [
                    'pregunta_id' => (int) $r->pregunta_id,
                    'opcion_id' => (int) $r->opcion_id,
                    'es_correcta' => (bool) $r->es_correcta,
                ])->all(),
        );
    }
}
