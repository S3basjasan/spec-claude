<?php

use App\Modules\Desarrollo\Domain\Exceptions\SeguimientoNoPermitido;
use App\Modules\Desarrollo\Domain\Services\ReglasDeSeguimiento;
use App\Modules\Desarrollo\Domain\ValueObjects\AsistenciaParticipante as A;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoAccion as E;

// Actividad que terminó el 2026-03-10: hito 30 vence el 2026-04-09 (ventana hasta el 24), hito 60 el 2026-05-09, hito 90 el 2026-06-08.
function motivoSeguimiento(int $hito = 30, string $hoy = '2026-04-09', E $estado = E::Finalizada, A $asistencia = A::Presente, bool $ya = false): ?string
{
    return (new ReglasDeSeguimiento)->motivoDeRechazo($hito, $estado, $asistencia, new DateTimeImmutable('2026-03-10'), new DateTimeImmutable($hoy), $ya);
}

it('acepta desde el día del vencimiento hasta 15 días después, inclusive', function (string $hoy) {
    expect(motivoSeguimiento(30, $hoy))->toBeNull();
})->with(['vencimiento' => '2026-04-09', 'medio' => '2026-04-15', 'último día' => '2026-04-24']);

it('rechaza un día antes del vencimiento', function () {
    expect(motivoSeguimiento(30, '2026-04-08'))->toBe(SeguimientoNoPermitido::HITO_NO_VENCIDO);
});

it('rechaza al día 16 de la ventana', function () {
    expect(motivoSeguimiento(30, '2026-04-25'))->toBe(SeguimientoNoPermitido::VENTANA_CERRADA);
});

it('rechaza hitos que no son 30, 60 ni 90', function (int $hito) {
    expect(motivoSeguimiento($hito))->toBe(SeguimientoNoPermitido::HITO_INVALIDO);
})->with([0, 45, 91, -30]);

it('aplica a los tres hitos', function () {
    expect(motivoSeguimiento(60, '2026-05-09'))->toBeNull()
        ->and(motivoSeguimiento(90, '2026-06-08'))->toBeNull()
        ->and(motivoSeguimiento(90, '2026-06-07'))->toBe(SeguimientoNoPermitido::HITO_NO_VENCIDO);
});

it('rechaza si la actividad no está finalizada', function (E $estado) {
    expect(motivoSeguimiento(estado: $estado))->toBe(SeguimientoNoPermitido::ACTIVIDAD_NO_FINALIZADA);
})->with([E::Programada, E::EnCurso, E::Cancelada]);

it('rechaza sin asistencia presente', function (A $asistencia) {
    expect(motivoSeguimiento(asistencia: $asistencia))->toBe(SeguimientoNoPermitido::SIN_ASISTENCIA);
})->with([A::Ausente, A::Justificado, A::Pendiente]);

it('rechaza repetir el mismo hito', function () {
    expect(motivoSeguimiento(ya: true))->toBe(SeguimientoNoPermitido::YA_RESPONDIDA);
});

it('lista los hitos pendientes dentro de su ventana', function () {
    $r = new ReglasDeSeguimiento;
    $fin = new DateTimeImmutable('2026-03-10');

    $dia = fn (string $hoy, array $respondidos = []) => array_map(fn ($h) => $h->value, $r->pendientes($fin, new DateTimeImmutable($hoy), $respondidos));

    expect($dia('2026-04-08'))->toBe([])
        ->and($dia('2026-04-09'))->toBe([30])
        ->and($dia('2026-04-09', [30]))->toBe([])
        ->and($dia('2026-04-25'))->toBe([])
        ->and($dia('2026-05-12'))->toBe([60])
        ->and($dia('2026-06-10', [60]))->toBe([90]);
});

it('no depende de una fecha de activación: una actividad terminada hace 35 días ofrece su hito de 30 días', function () {
    $pendientes = (new ReglasDeSeguimiento)->pendientes(new DateTimeImmutable('2026-03-10'), new DateTimeImmutable('2026-04-14'), []);

    expect(array_map(fn ($h) => $h->value, $pendientes))->toBe([30]);
});
