<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\EncuestaSatisfaccionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\ImpactoAccionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\SeguimientoImpactoModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

it('crea las tres tablas de reportes con sus columnas', function () {
    expect(Schema::hasColumns('encuestas_satisfaccion', ['id', 'tenant_id', 'actividad_id', 'colaborador_id', 'calificacion', 'comentario', 'created_at']))->toBeTrue()
        ->and(Schema::hasColumns('seguimientos_impacto', ['id', 'tenant_id', 'actividad_id', 'colaborador_id', 'hito', 'aplicacion', 'apoyo', 'comentario', 'created_at']))->toBeTrue()
        ->and(Schema::hasColumns('impactos_accion', ['id', 'tenant_id', 'plan_accion_id', 'indicador', 'unidad', 'valor_antes', 'valor_despues', 'beneficio_estimado', 'justificacion', 'created_by', 'updated_by', 'created_at', 'updated_at']))->toBeTrue();
});

it('no tiene updated_at en encuestas ni en seguimientos (inmutables)', function () {
    expect(Schema::hasColumn('encuestas_satisfaccion', 'updated_at'))->toBeFalse()
        ->and(Schema::hasColumn('seguimientos_impacto', 'updated_at'))->toBeFalse();
});

it('impide una segunda encuesta del mismo colaborador en la misma actividad', function () {
    $datos = ['tenant_id' => 1, 'actividad_id' => 5, 'colaborador_id' => 7, 'calificacion' => 4];
    EncuestaSatisfaccionModel::query()->create($datos);

    expect(fn () => EncuestaSatisfaccionModel::query()->create($datos))->toThrow(QueryException::class);
    EncuestaSatisfaccionModel::query()->create([...$datos, 'colaborador_id' => 8]);
    expect(EncuestaSatisfaccionModel::query()->count())->toBe(2);
});

it('impide repetir un hito del mismo colaborador y actividad, pero admite otro hito', function () {
    $datos = ['tenant_id' => 1, 'actividad_id' => 5, 'colaborador_id' => 7, 'hito' => 30, 'aplicacion' => 4, 'apoyo' => 3];
    SeguimientoImpactoModel::query()->create($datos);

    expect(fn () => SeguimientoImpactoModel::query()->create($datos))->toThrow(QueryException::class);
    SeguimientoImpactoModel::query()->create([...$datos, 'hito' => 60]);
    expect(SeguimientoImpactoModel::query()->count())->toBe(2);
});

it('permite una sola evaluación de impacto por acción', function () {
    $datos = [
        'tenant_id' => 1, 'plan_accion_id' => 9, 'indicador' => 'Tiempo de cierre', 'unidad' => 'días', 'valor_antes' => 10, 'valor_despues' => 7,
        'beneficio_estimado' => 1500.5, 'justificacion' => 'Menos reprocesos', 'created_by' => 1, 'updated_by' => 1,
    ];
    ImpactoAccionModel::query()->create($datos);

    expect(fn () => ImpactoAccionModel::query()->create($datos))->toThrow(QueryException::class);
    expect(ImpactoAccionModel::query()->first()->beneficio_estimado)->toBe(1500.5);
});
