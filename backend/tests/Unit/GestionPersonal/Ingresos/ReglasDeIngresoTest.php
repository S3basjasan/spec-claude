<?php

use App\Modules\GestionPersonal\Domain\Entities\AsignacionLaboral;
use App\Modules\GestionPersonal\Domain\Entities\VinculoLaboral;
use App\Modules\GestionPersonal\Domain\Services\ReglasDeIngreso;

const CONTRATOS_REGLAS = ['indefinido', 'plazo_fijo', 'temporal', 'servicios_profesionales'];

it('acepta fecha, contrato y posición válidos', function () {
    expect((new ReglasDeIngreso)->errores('2026-10-15', 'indefinido', 7, '2026-10-15', CONTRATOS_REGLAS))->toBe([]);
});

it('exige fecha con formato AAAA-MM-DD dentro del rango', function () {
    $reglas = new ReglasDeIngreso;

    foreach ([null, '', '15/10/2026', '2026-02-30', '1899-12-31', '2037-10-16'] as $fecha) {
        expect($reglas->errores($fecha, 'indefinido', 1, '2026-10-15', CONTRATOS_REGLAS))->toHaveKey('fecha_ingreso');
    }

    expect($reglas->errores('2036-10-15', 'indefinido', 1, '2026-10-15', CONTRATOS_REGLAS))->toBe([]);
});

it('exige contrato entre las claves habilitadas y posición', function () {
    $reglas = new ReglasDeIngreso;

    expect($reglas->errores('2026-10-15', null, 1, '2026-10-15', CONTRATOS_REGLAS))->toHaveKey('tipo_contrato')
        ->and($reglas->errores('2026-10-15', 'honorarios', 1, '2026-10-15', CONTRATOS_REGLAS))->toHaveKey('tipo_contrato')
        ->and($reglas->errores('2026-10-15', 'indefinido', null, '2026-10-15', CONTRATOS_REGLAS))->toHaveKey('posicion_id')
        ->and($reglas->errores('2026-10-15', 'indefinido', 0, '2026-10-15', CONTRATOS_REGLAS))->toHaveKey('posicion_id');
});

it('detecta el vínculo que se superpone con un ingreso abierto, incluidos los futuros', function () {
    $reglas = new ReglasDeIngreso;
    $cerrado = new VinculoLaboral(1, 1, 1, '2020-01-01', '2026-01-01', 'indefinido', 1, 1, 'x');
    $futuro = new VinculoLaboral(2, 1, 1, '2027-01-01', null, 'indefinido', 1, 1, 'x');

    expect($reglas->vinculoEnConflicto('2026-01-01', [$cerrado]))->toBeNull()
        ->and($reglas->vinculoEnConflicto('2025-12-31', [$cerrado])?->id)->toBe(1)
        ->and($reglas->vinculoEnConflicto('2026-06-01', [$cerrado, $futuro])?->id)->toBe(2);
});

it('detecta la asignación de la posición que se superpone', function () {
    $reglas = new ReglasDeIngreso;
    $actual = new AsignacionLaboral(1, 1, 5, '2026-01-01', null);
    $termina = new AsignacionLaboral(2, 2, 5, '2026-01-01', '2026-12-01');

    expect($reglas->asignacionEnConflicto('2026-10-15', [$actual]))->not->toBeNull()
        ->and($reglas->asignacionEnConflicto('2026-12-01', [$termina]))->toBeNull()
        ->and($reglas->asignacionEnConflicto('2026-11-30', [$termina]))->not->toBeNull();
});
