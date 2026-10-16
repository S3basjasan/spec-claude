<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function insertarVinculoCrudo(int $fichaId, string $ingreso, ?string $salida): int
{
    return DB::table('vinculos_laborales')->insertGetId([
        'tenant_id' => 1, 'colaborador_id' => $fichaId, 'fecha_ingreso' => $ingreso, 'fecha_salida' => $salida,
        'tipo_contrato_clave' => 'indefinido', 'version' => 1,
        'operacion_id' => (string) Str::uuid(), 'operacion_hash' => str_repeat('a', 64),
        'created_by' => 1, 'updated_by' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function insertarAsignacionCruda(int $vinculoId, int $posicionId, string $inicio, ?string $fin): int
{
    return DB::table('asignaciones_laborales')->insertGetId([
        'tenant_id' => 1, 'vinculo_id' => $vinculoId, 'posicion_id' => $posicionId, 'fecha_inicio' => $inicio,
        'fecha_fin' => $fin, 'version' => 1, 'created_by' => 1, 'updated_by' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function mensajeDe(callable $accion): string
{
    try {
        $accion();
    } catch (QueryException $e) {
        return $e->getMessage();
    }

    return '';
}

it('el CHECK rechaza una salida anterior al ingreso y un fin anterior al inicio', function () {
    $ficha = fichaDe(['numero_identificacion' => '1']);

    expect(mensajeDe(fn () => insertarVinculoCrudo($ficha->id, '2026-05-01', '2026-04-01')))->toContain('CHECK');

    $vinculo = insertarVinculoCrudo($ficha->id, '2026-01-01', null);
    expect(mensajeDe(fn () => insertarAsignacionCruda($vinculo, 1, '2026-05-01', '2026-04-01')))->toContain('CHECK');
});

it('un vínculo que se superpone con otro de la misma ficha aborta, y los contiguos se aceptan', function () {
    $ficha = fichaDe(['numero_identificacion' => '2']);
    insertarVinculoCrudo($ficha->id, '2020-01-01', '2026-01-01');

    expect(mensajeDe(fn () => insertarVinculoCrudo($ficha->id, '2025-12-31', '2027-01-01')))->toContain('VINCULO_SUPERPUESTO');

    $contiguo = insertarVinculoCrudo($ficha->id, '2026-01-01', null);
    expect($contiguo)->toBeInt();
});

it('un vínculo de otra ficha en las mismas fechas se acepta', function () {
    $a = fichaDe(['numero_identificacion' => '3']);
    $b = fichaDe(['numero_identificacion' => '4']);
    insertarVinculoCrudo($a->id, '2020-01-01', null);

    expect(insertarVinculoCrudo($b->id, '2020-01-01', null))->toBeInt();
});

it('una posición no admite dos asignaciones superpuestas ni dos asignaciones por vínculo', function () {
    $fichaA = fichaDe(['numero_identificacion' => '5']);
    $fichaB = fichaDe(['numero_identificacion' => '6']);
    $va = insertarVinculoCrudo($fichaA->id, '2020-01-01', '2026-01-01');
    $vb = insertarVinculoCrudo($fichaB->id, '2025-06-01', null);
    insertarAsignacionCruda($va, 9, '2020-01-01', '2026-01-01');

    expect(mensajeDe(fn () => insertarAsignacionCruda($vb, 9, '2025-12-31', null)))->toContain('POSICION_SUPERPUESTA')
        ->and(insertarAsignacionCruda($vb, 9, '2026-01-01', null))->toBeInt()
        ->and(mensajeDe(fn () => insertarAsignacionCruda($vb, 10, '2026-02-01', null)))->not->toBe('');
});

it('la asignación debe estar contenida en su vínculo y el vínculo no puede acortarse sobre ella', function () {
    $ficha = fichaDe(['numero_identificacion' => '7']);
    $vinculo = insertarVinculoCrudo($ficha->id, '2026-03-01', '2026-09-01');

    expect(mensajeDe(fn () => insertarAsignacionCruda($vinculo, 3, '2026-02-01', '2026-09-01')))->toContain('ASIGNACION_FUERA_DE_VINCULO')
        ->and(mensajeDe(fn () => insertarAsignacionCruda($vinculo, 3, '2026-03-01', '2026-10-01')))->toContain('ASIGNACION_FUERA_DE_VINCULO');

    insertarAsignacionCruda($vinculo, 3, '2026-03-01', '2026-09-01');

    expect(mensajeDe(fn () => DB::table('vinculos_laborales')->where('id', $vinculo)->update(['fecha_salida' => '2026-06-01'])))
        ->toContain('VINCULO_ACORTADO_SOBRE_ASIGNACION');
});

it('un intervalo vacío no ocupa la posición', function () {
    $ficha = fichaDe(['numero_identificacion' => '8']);
    $vinculo = insertarVinculoCrudo($ficha->id, '2026-05-01', '2026-05-01');
    insertarAsignacionCruda($vinculo, 4, '2026-05-01', '2026-05-01');

    $otra = fichaDe(['numero_identificacion' => '9']);
    $vinculoB = insertarVinculoCrudo($otra->id, '2026-01-01', null);

    expect(insertarAsignacionCruda($vinculoB, 4, '2026-05-01', null))->toBeInt();
});
