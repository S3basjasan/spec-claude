<?php

use App\Modules\Desarrollo\Domain\Entities\InscripcionARuta;
use App\Modules\Desarrollo\Domain\Services\CalculadorDeCantera;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDeInscripcion;

function inscripcionCantera(int $colaborador, ?int $actual, EstadoDeInscripcion $estado = EstadoDeInscripcion::Activa): InscripcionARuta
{
    return new InscripcionARuta($colaborador, 1, 7, $colaborador, $actual, null, $estado, null, null, null, 1);
}

it('cuenta las inscripciones Activas por nivel actual', function () {
    $r = (new CalculadorDeCantera)->contar([
        inscripcionCantera(1, 100), inscripcionCantera(2, 100), inscripcionCantera(3, 102),
    ], [100, 101, 102]);

    expect($r['por_nivel'])->toBe([100 => 2, 101 => 0, 102 => 1])->and($r['sin_nivel'])->toBe(0)->and($r['total'])->toBe(3);
});

it('excluye las Finalizadas y las Retiradas', function () {
    $r = (new CalculadorDeCantera)->contar([
        inscripcionCantera(1, 100),
        inscripcionCantera(2, 100, EstadoDeInscripcion::Finalizada),
        inscripcionCantera(3, 101, EstadoDeInscripcion::Retirada),
    ], [100, 101]);

    expect($r['por_nivel'])->toBe([100 => 1, 101 => 0])->and($r['total'])->toBe(1);
});

it('informa aparte las Activas sin nivel actual definido', function () {
    $r = (new CalculadorDeCantera)->contar([inscripcionCantera(1, null), inscripcionCantera(2, 100), inscripcionCantera(3, null)], [100, 101]);

    expect($r['por_nivel'])->toBe([100 => 1, 101 => 0])->and($r['sin_nivel'])->toBe(2)->and($r['total'])->toBe(3);
});

it('una ruta sin inscripciones tiene todos los niveles en cero', function () {
    $r = (new CalculadorDeCantera)->contar([], [100, 101]);

    expect($r)->toBe(['por_nivel' => [100 => 0, 101 => 0], 'sin_nivel' => 0, 'total' => 0]);
});
