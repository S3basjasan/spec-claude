<?php

use App\Modules\GestionPersonal\Domain\Entities\VinculoLaboral;
use App\Modules\GestionPersonal\Domain\Services\SituacionLaboralDerivada;
use App\Modules\GestionPersonal\Domain\ValueObjects\SituacionLaboral;

function vinculoUnit(string $ingreso, ?string $salida = null, int $id = 1): VinculoLaboral
{
    return new VinculoLaboral($id, 1, 1, $ingreso, $salida, 'indefinido', 1, 1, '2026-01-01T00:00:00Z');
}

it('Sin vínculo vigente sin vínculos', function () {
    $situacion = (new SituacionLaboralDerivada)->paraFecha([], '2026-10-15');

    expect($situacion->estado)->toBe(SituacionLaboral::SIN_VINCULO)
        ->and($situacion->vigente)->toBeNull()
        ->and($situacion->proximo)->toBeNull();
});

it('Activo con un vínculo iniciado y no terminado', function () {
    $derivada = new SituacionLaboralDerivada;

    expect($derivada->paraFecha([vinculoUnit('2026-10-15')], '2026-10-15')->estado)->toBe(SituacionLaboral::ACTIVO)
        ->and($derivada->paraFecha([vinculoUnit('2026-01-01', '2026-12-01')], '2026-10-15')->estado)->toBe(SituacionLaboral::ACTIVO);
});

it('un primer ingreso futuro es Sin vínculo vigente con próximo ingreso y pasa a Activo al llegar la fecha', function () {
    $derivada = new SituacionLaboralDerivada;
    $futuro = [vinculoUnit('2026-11-01')];

    $antes = $derivada->paraFecha($futuro, '2026-10-31');
    $dia = $derivada->paraFecha($futuro, '2026-11-01');

    expect($antes->estado)->toBe(SituacionLaboral::SIN_VINCULO)
        ->and($antes->proximo?->fechaIngreso)->toBe('2026-11-01')
        ->and($dia->estado)->toBe(SituacionLaboral::ACTIVO)
        ->and($dia->proximo)->toBeNull();
});

it('Desvinculado con una salida efectiva; un reingreso futuro no cambia el estado', function () {
    $derivada = new SituacionLaboralDerivada;
    $vinculos = [vinculoUnit('2020-01-01', '2026-01-01', 1), vinculoUnit('2027-01-01', null, 2)];

    $situacion = $derivada->paraFecha($vinculos, '2026-10-15');

    expect($situacion->estado)->toBe(SituacionLaboral::DESVINCULADO)
        ->and($situacion->proximo?->id)->toBe(2);
});

it('la salida el mismo día deja de ser vigente (fin excluido)', function () {
    $situacion = (new SituacionLaboralDerivada)->paraFecha([vinculoUnit('2020-01-01', '2026-10-15')], '2026-10-15');

    expect($situacion->estado)->toBe(SituacionLaboral::DESVINCULADO);
});
