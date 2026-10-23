<?php

use App\Modules\Desarrollo\Domain\Services\DerivadorDeEstadoDeAccion;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDeAccionPdi as E;

function pdiDerivar(bool $cancelada, ?string $actividad, ?string $asistencia, string $hoy = '2026-08-01'): E
{
    return (new DerivadorDeEstadoDeAccion)->derivar($cancelada, $actividad, $asistencia, new DateTimeImmutable($hoy), new DateTimeImmutable('2026-07-15'), new DateTimeImmutable('2026-09-30'));
}

it('una acción del plan cancelada o una actividad Cancelada dejan la acción Cancelada', function () {
    expect(pdiDerivar(true, null, null))->toBe(E::Cancelada)
        ->and(pdiDerivar(false, 'Cancelada', 'Presente'))->toBe(E::Cancelada)
        ->and(pdiDerivar(true, 'Finalizada', 'Presente'))->toBe(E::Cancelada);
});

it('una actividad Finalizada con asistencia Presente deja la acción Cumplida', function () {
    expect(pdiDerivar(false, 'Finalizada', 'Presente'))->toBe(E::Cumplida);
});

it('Ausente, Justificado o Pendiente no cumplen aunque la actividad esté Finalizada', function () {
    foreach (['Ausente', 'Justificado', 'Pendiente', null] as $asistencia) {
        expect(pdiDerivar(false, 'Finalizada', $asistencia))->not->toBe(E::Cumplida);
    }
});

it('sin cumplimiento ni cancelación es En curso dentro de las fechas de la acción del PDI y Pendiente fuera', function () {
    expect(pdiDerivar(false, 'EnCurso', 'Pendiente', '2026-07-15'))->toBe(E::EnCurso)
        ->and(pdiDerivar(false, null, null, '2026-09-30'))->toBe(E::EnCurso)
        ->and(pdiDerivar(false, null, null, '2026-07-14'))->toBe(E::Pendiente)
        ->and(pdiDerivar(false, 'Programada', null, '2026-10-01'))->toBe(E::Pendiente);
});

it('una asistencia Presente sin actividad Finalizada no cumple', function () {
    expect(pdiDerivar(false, 'EnCurso', 'Presente'))->toBe(E::EnCurso)
        ->and(pdiDerivar(false, null, 'Presente'))->toBe(E::EnCurso);
});
