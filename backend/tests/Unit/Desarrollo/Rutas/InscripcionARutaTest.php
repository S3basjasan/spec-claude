<?php

use App\Modules\Desarrollo\Domain\Entities\InscripcionARuta;
use App\Modules\Desarrollo\Domain\Exceptions\InscripcionNoPermitida;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDeInscripcion;

const ORDENES = [100 => 1, 101 => 2, 102 => 3];

function inscripcionDeDominio(?int $actual = 100, ?int $objetivo = 102, EstadoDeInscripcion $estado = EstadoDeInscripcion::Activa): InscripcionARuta
{
    return new InscripcionARuta(1, 1, 7, 10, $actual, $objetivo, $estado, null, null, null, 1);
}

it('el nivel objetivo debe ser posterior al actual', function () {
    expect(fn () => InscripcionARuta::validarNiveles(ORDENES, 101, 101))->toThrow(InscripcionNoPermitida::class)
        ->and(fn () => InscripcionARuta::validarNiveles(ORDENES, 102, 100))->toThrow(InscripcionNoPermitida::class);

    InscripcionARuta::validarNiveles(ORDENES, 100, 101);
    expect(true)->toBeTrue();
});

it('el nivel actual puede quedar sin definir y entonces cualquier objetivo vale', function () {
    InscripcionARuta::validarNiveles(ORDENES, null, 100);
    InscripcionARuta::validarNiveles(ORDENES, null, null);
    InscripcionARuta::validarNiveles(ORDENES, 100, null);

    expect(true)->toBeTrue();
});

it('rechaza niveles que no pertenecen a la ruta, indicando el campo', function () {
    try {
        InscripcionARuta::validarNiveles(ORDENES, 999, null);
    } catch (InscripcionNoPermitida $e) {
        expect($e->codigoError())->toBe('nivel_ajeno')->and($e->errores())->toHaveKey('nivel_actual_id');
    }
    try {
        InscripcionARuta::validarNiveles(ORDENES, null, 999);
    } catch (InscripcionNoPermitida $e) {
        expect($e->errores())->toHaveKey('nivel_objetivo_id');
    }
});

it('registrar el nivel alcanzado mueve el actual al objetivo y deja el objetivo sin definir si no era el último', function () {
    $despues = inscripcionDeDominio(100, 101)->alcanzada(ORDENES, 3, 5, new DateTimeImmutable('2026-06-15'));

    expect($despues->nivelActualId)->toBe(101)->and($despues->nivelObjetivoId)->toBeNull()
        ->and($despues->estado)->toBe(EstadoDeInscripcion::Activa)->and($despues->resueltaEn)->toBeNull()->and($despues->resueltaPor)->toBeNull();
});

it('si el objetivo era el último nivel, la inscripción pasa a Finalizada con fecha de resolución', function () {
    $ahora = new DateTimeImmutable('2026-06-15 10:00:00');
    $despues = inscripcionDeDominio(100, 102)->alcanzada(ORDENES, 3, 5, $ahora);

    expect($despues->nivelActualId)->toBe(102)->and($despues->estado)->toBe(EstadoDeInscripcion::Finalizada)
        ->and($despues->resueltaEn)->toEqual($ahora)->and($despues->resueltaPor)->toBe(5);
});

it('no se puede registrar el nivel alcanzado sin nivel objetivo', function () {
    expect(fn () => inscripcionDeDominio(100, null)->alcanzada(ORDENES, 3, 5, new DateTimeImmutable))->toThrow(InscripcionNoPermitida::class);
});

it('retirar exige un motivo y deja la inscripción Retirada con fecha de resolución y los niveles intactos', function () {
    $ahora = new DateTimeImmutable('2026-06-15 10:00:00');
    $retirada = inscripcionDeDominio(100, 102)->retirada('  Cambió de área  ', 5, $ahora);

    expect($retirada->estado)->toBe(EstadoDeInscripcion::Retirada)->and($retirada->motivo)->toBe('Cambió de área')
        ->and($retirada->resueltaEn)->toEqual($ahora)->and($retirada->resueltaPor)->toBe(5)
        ->and($retirada->nivelActualId)->toBe(100)->and($retirada->nivelObjetivoId)->toBe(102);

    expect(fn () => inscripcionDeDominio()->retirada('   ', 5, $ahora))->toThrow(InscripcionNoPermitida::class)
        ->and(fn () => inscripcionDeDominio()->retirada(str_repeat('m', 501), 5, $ahora))->toThrow(InscripcionNoPermitida::class);
});

it('las inscripciones Finalizadas y Retiradas no admiten cambios', function (EstadoDeInscripcion $estado) {
    $i = inscripcionDeDominio(100, 102, $estado);

    expect(fn () => $i->conNiveles(100, 101, ORDENES))->toThrow(InscripcionNoPermitida::class)
        ->and(fn () => $i->alcanzada(ORDENES, 3, 5, new DateTimeImmutable))->toThrow(InscripcionNoPermitida::class)
        ->and(fn () => $i->retirada('Motivo', 5, new DateTimeImmutable))->toThrow(InscripcionNoPermitida::class);
})->with([EstadoDeInscripcion::Finalizada, EstadoDeInscripcion::Retirada]);

it('cambiar los niveles valida el orden y conserva el estado', function () {
    $cambiada = inscripcionDeDominio(100, 101)->conNiveles(101, 102, ORDENES);

    expect($cambiada->nivelActualId)->toBe(101)->and($cambiada->nivelObjetivoId)->toBe(102)->and($cambiada->estado)->toBe(EstadoDeInscripcion::Activa);
    expect(fn () => inscripcionDeDominio()->conNiveles(102, 101, ORDENES))->toThrow(InscripcionNoPermitida::class);
});
