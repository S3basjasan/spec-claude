<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\ActividadAsistenciaModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\ActividadModel;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('crea las tres tablas con sus columnas', function () {
    expect(Schema::getColumnListing('actividades'))->toContain('tenant_id', 'numero', 'codigo', 'plan_accion_id', 'lugar_enlace', 'departamento_id', 'version', 'created_by', 'updated_by')
        ->and(Schema::getColumnListing('actividad_asistencias'))->toContain('actividad_id', 'colaborador_id', 'asistencia', 'calificacion', 'observaciones', 'registrado_by')
        ->and(Schema::getColumnListing('actividad_asistencia_historial'))->toContain('actividad_id', 'colaborador_id', 'asistencia_anterior', 'asistencia_nueva', 'calificacion_anterior', 'calificacion_nueva', 'user_id', 'created_at')
        ->and(Schema::getColumnListing('actividad_asistencia_historial'))->not->toContain('updated_at');
});

it('la versión empieza en 1', function () {
    actividadFalsos();
    [, $accion] = accionEnPlan();

    expect(ActividadModel::query()->findOrFail(actividadDe($accion)['id'])->version)->toBe(1);
});

it('no admite dos actividades para la misma acción', function () {
    actividadFalsos();
    [, $accion] = accionEnPlan();
    actividadDe($accion);

    expect(fn () => ActividadModel::query()->create([
        'tenant_id' => 1, 'numero' => 99, 'codigo' => 'ACT-0099', 'plan_accion_id' => $accion, 'lugar_enlace' => 'x', 'created_by' => 1, 'updated_by' => 1,
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('no admite dos registros del mismo colaborador en una actividad', function () {
    actividadFalsos();
    [, $accion] = accionEnPlan();
    $id = actividadDe($accion)['id'];
    registroDeAsistencia($id, 10, 'Presente');

    expect(fn () => registroDeAsistencia($id, 10, 'Ausente'))->toThrow(UniqueConstraintViolationException::class);
});

it('no permite borrar una actividad con registros', function () {
    actividadFalsos();
    [, $accion] = accionEnPlan();
    $id = actividadDe($accion)['id'];
    registroDeAsistencia($id, 10, 'Presente');

    DB::statement('PRAGMA foreign_keys = ON');
    expect(fn () => ActividadModel::query()->whereKey($id)->delete())->toThrow(QueryException::class)
        ->and(ActividadAsistenciaModel::query()->count())->toBe(1);
});
