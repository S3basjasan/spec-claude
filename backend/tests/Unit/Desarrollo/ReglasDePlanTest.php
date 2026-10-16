<?php

use App\Modules\Desarrollo\Domain\Entities\AccionFormativa;
use App\Modules\Desarrollo\Domain\Entities\Plan;
use App\Modules\Desarrollo\Domain\Services\CalculadorDeEstadoDeAccion;
use App\Modules\Desarrollo\Domain\Services\DetectorDeCruces;
use App\Modules\Desarrollo\Domain\Services\NormalizadorDeNombre;
use App\Modules\Desarrollo\Domain\Services\ReglasDePlan;
use App\Modules\Desarrollo\Domain\ValueObjects\CodigoPlan;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoAccion;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoPlan;
use App\Modules\Desarrollo\Domain\ValueObjects\OperacionPlan;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoAccion;

function accionPura(array $c = []): AccionFormativa
{
    return new AccionFormativa(
        $c['id'] ?? 1,
        $c['nombre'] ?? 'Acción',
        null,
        array_key_exists('tipo', $c) ? $c['tipo'] : TipoAccion::Virtual,
        array_key_exists('inicio', $c) ? $c['inicio'] : new DateTimeImmutable('2026-03-02'),
        array_key_exists('fin', $c) ? $c['fin'] : new DateTimeImmutable('2026-03-06'),
        array_key_exists('horas', $c) ? $c['horas'] : 16.0,
        array_key_exists('formador', $c) ? $c['formador'] : 'Ana',
        array_key_exists('capacidad', $c) ? $c['capacidad'] : 20,
        array_key_exists('costo', $c) ? $c['costo'] : 100.0,
        $c['cancelada'] ?? false,
        null,
        $c['competencias'] ?? [1],
        $c['participantes'] ?? [10],
    );
}

function planPuro(EstadoPlan $estado = EstadoPlan::Borrador, array $acciones = [], ?int $aprobador = 5): Plan
{
    return new Plan(1, 1, 1, 'Plan', 'plan', 2026, null, $estado, $aprobador, null, null, 1, 1, null, null, $acciones);
}

it('permite solo las transiciones del ciclo de vida', function () {
    $reglas = new ReglasDePlan;
    $permitidas = [
        ['Borrador', 'Revision'], ['Revision', 'Aprobado'], ['Revision', 'Borrador'], ['Aprobado', 'Ejecutando'], ['Ejecutando', 'Cerrado'],
    ];
    foreach (EstadoPlan::cases() as $de) {
        foreach (EstadoPlan::cases() as $a) {
            $esperado = in_array([$de->value, $a->value], $permitidas, true);
            expect($reglas->transicionPermitida($de, $a))->toBe($esperado, "{$de->value} -> {$a->value}");
        }
    }
});

it('aplica las reglas de edición por estado', function () {
    $reglas = new ReglasDePlan;
    $tabla = [
        'Borrador' => [true, true, false, false],
        'Revision' => [false, false, false, true],
        'Aprobado' => [false, true, true, false],
        'Ejecutando' => [false, true, true, false],
        'Cerrado' => [false, false, false, false],
    ];
    foreach ($tabla as $estado => [$editar, $participantes, $cancelar, $reasignar]) {
        $e = EstadoPlan::from($estado);
        expect($reglas->permite($e, OperacionPlan::Editar))->toBe($editar, "$estado editar")
            ->and($reglas->permite($e, OperacionPlan::CambiarParticipantes))->toBe($participantes, "$estado participantes")
            ->and($reglas->permite($e, OperacionPlan::CancelarAccion))->toBe($cancelar, "$estado cancelar")
            ->and($reglas->permite($e, OperacionPlan::ReasignarAprobador))->toBe($reasignar, "$estado reasignar");
    }
});

it('no marca faltantes en un plan completo', function () {
    expect((new ReglasDePlan)->faltantes(planPuro(acciones: [accionPura()])))->toBe([]);
});

it('lista cada dato que falta para enviar a revisión', function () {
    $reglas = new ReglasDePlan;
    expect($reglas->faltantes(planPuro(aprobador: null)))->toBe([
        ['accion_id' => null, 'campo' => 'acciones'],
        ['accion_id' => null, 'campo' => 'aprobador'],
    ]);

    $campos = [
        'tipo' => ['tipo' => null], 'fechas' => ['inicio' => null], 'duracion_horas' => ['horas' => null],
        'formador' => ['formador' => ' '], 'capacidad_maxima' => ['capacidad' => null], 'costo' => ['costo' => null],
        'participantes' => ['participantes' => []], 'competencias' => ['competencias' => []],
    ];
    foreach ($campos as $campo => $cambio) {
        $faltantes = $reglas->faltantes(planPuro(acciones: [accionPura($cambio + ['id' => 7])]));
        expect($faltantes)->toBe([['accion_id' => 7, 'campo' => $campo]], $campo);
    }
});

it('solo permite cerrar cuando todas las acciones están finalizadas o canceladas', function () {
    $reglas = new ReglasDePlan;
    expect($reglas->puedeCerrar([EstadoAccion::Finalizada, EstadoAccion::Cancelada]))->toBeTrue()
        ->and($reglas->puedeCerrar([EstadoAccion::Cancelada]))->toBeTrue()
        ->and($reglas->puedeCerrar([EstadoAccion::Finalizada, EstadoAccion::EnCurso]))->toBeFalse()
        ->and($reglas->puedeCerrar([EstadoAccion::Programada]))->toBeFalse();
});

it('calcula los botones: aprobar y devolver solo para el aprobador designado', function () {
    $reglas = new ReglasDePlan;
    $plan = planPuro(EstadoPlan::Revision);

    expect($reglas->puede($plan, 5)['aprobar'])->toBeTrue()
        ->and($reglas->puede($plan, 5)['devolver'])->toBeTrue()
        ->and($reglas->puede($plan, 6)['aprobar'])->toBeFalse()
        ->and($reglas->puede($plan, 6)['devolver'])->toBeFalse()
        ->and($reglas->puede($plan, 6)['reasignar_aprobador'])->toBeTrue()
        ->and($reglas->puede(planPuro(EstadoPlan::Aprobado), 6)['iniciar'])->toBeTrue()
        ->and($reglas->puede(planPuro(EstadoPlan::Ejecutando), 6)['cerrar'])->toBeTrue()
        ->and($reglas->puede(planPuro(EstadoPlan::Cerrado), 5))->each->toBeFalse();
});

it('calcula el estado de la acción en los bordes', function () {
    $c = new CalculadorDeEstadoDeAccion;
    $inicio = new DateTimeImmutable('2026-03-10');
    $fin = new DateTimeImmutable('2026-03-12');

    expect($c->calcular(false, $inicio, $fin, new DateTimeImmutable('2026-03-09')))->toBe(EstadoAccion::Programada)
        ->and($c->calcular(false, $inicio, $fin, new DateTimeImmutable('2026-03-10')))->toBe(EstadoAccion::EnCurso)
        ->and($c->calcular(false, $inicio, $fin, new DateTimeImmutable('2026-03-12')))->toBe(EstadoAccion::EnCurso)
        ->and($c->calcular(false, $inicio, $fin, new DateTimeImmutable('2026-03-13')))->toBe(EstadoAccion::Finalizada)
        ->and($c->calcular(false, null, null, new DateTimeImmutable('2026-03-13')))->toBe(EstadoAccion::Programada)
        ->and($c->calcular(true, $inicio, $fin, new DateTimeImmutable('2026-03-20')))->toBe(EstadoAccion::Cancelada);
});

it('detecta cruces entre acciones del mismo colaborador, incluso si solo coincide un día', function () {
    $d = new DetectorDeCruces;
    $avisos = $d->detectar([
        ['accion_id' => 1, 'plan_id' => 1, 'colaborador_id' => 10, 'inicio' => '2026-03-01', 'fin' => '2026-03-05'],
        ['accion_id' => 2, 'plan_id' => 2, 'colaborador_id' => 10, 'inicio' => '2026-03-05', 'fin' => '2026-03-09'],
        ['accion_id' => 3, 'plan_id' => 1, 'colaborador_id' => 11, 'inicio' => '2026-03-01', 'fin' => '2026-03-09'],
        ['accion_id' => 4, 'plan_id' => 1, 'colaborador_id' => 10, 'inicio' => '2026-04-01', 'fin' => '2026-04-02'],
    ]);

    expect($avisos)->toBe([
        ['colaborador_id' => 10, 'accion_id' => 1, 'con_accion_id' => 2, 'con_plan_id' => 2],
        ['colaborador_id' => 10, 'accion_id' => 2, 'con_accion_id' => 1, 'con_plan_id' => 1],
    ]);
});

it('limita los cruces reportados a las acciones analizadas', function () {
    $d = new DetectorDeCruces;
    $avisos = $d->detectar([
        ['accion_id' => 1, 'plan_id' => 1, 'colaborador_id' => 10, 'inicio' => '2026-03-01', 'fin' => '2026-03-05'],
        ['accion_id' => 2, 'plan_id' => 2, 'colaborador_id' => 10, 'inicio' => '2026-03-02', 'fin' => '2026-03-03'],
    ], [1]);

    expect($avisos)->toHaveCount(1)->and($avisos[0]['accion_id'])->toBe(1);
});

it('normaliza el nombre sin distinguir mayúsculas ni espacios', function () {
    $n = new NormalizadorDeNombre;

    expect($n->normalizar('  Plan   TÉCNICO '))->toBe('plan técnico')
        ->and($n->limpiar('  Plan   Técnico '))->toBe('Plan Técnico')
        ->and(CodigoPlan::desde(7))->toBe('PLAN-0007');
});
