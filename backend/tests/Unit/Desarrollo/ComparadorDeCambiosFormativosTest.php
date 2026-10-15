<?php

use App\Modules\Desarrollo\Domain\Services\ComparadorDeCambiosFormativos;
use App\Modules\Desarrollo\Domain\ValueObjects\AccionHistorial;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoCambioHistorial;

function comparar(array $cursosAntes = [], array $cursosAhora = [], array $microsAntes = [], array $microsAhora = [], ?int $areaAntes = null, ?string $nombreAntes = null, ?int $areaAhora = null, ?string $nombreAhora = null): array
{
    return (new ComparadorDeCambiosFormativos)->comparar(5, $areaAntes, $nombreAntes, $areaAhora, $nombreAhora, $cursosAntes, $cursosAhora, $microsAntes, $microsAhora);
}

it('registra una entrada Asociado por cada curso nuevo', function () {
    $cambios = comparar(cursosAhora: [1 => 'Comunicación', 2 => 'Liderazgo']);

    expect($cambios)->toHaveCount(2)
        ->and($cambios[0]->tipoCambio)->toBe(TipoCambioHistorial::Curso)
        ->and($cambios[0]->accion)->toBe(AccionHistorial::Asociado)
        ->and($cambios[0]->valorNuevo)->toBe('Comunicación')
        ->and($cambios[0]->valorAnterior)->toBeNull()
        ->and($cambios[0]->competenciaId)->toBe(5);
});

it('registra una entrada Quitado por cada micro-contenido que sale', function () {
    $cambios = comparar(microsAntes: [3 => 'Guía rápida'], microsAhora: []);

    expect($cambios)->toHaveCount(1)
        ->and($cambios[0]->tipoCambio)->toBe(TipoCambioHistorial::Microcontenido)
        ->and($cambios[0]->accion)->toBe(AccionHistorial::Quitado)
        ->and($cambios[0]->valorAnterior)->toBe('Guía rápida')
        ->and($cambios[0]->recursoId)->toBe(3);
});

it('registra el cambio de área con los nombres anterior y nuevo', function () {
    $cambios = comparar(areaAntes: 1, nombreAntes: 'Técnica y datos', areaAhora: 2, nombreAhora: 'Liderazgo');

    expect($cambios)->toHaveCount(1)
        ->and($cambios[0]->tipoCambio)->toBe(TipoCambioHistorial::AreaFormativa)
        ->and($cambios[0]->accion)->toBe(AccionHistorial::Cambiado)
        ->and($cambios[0]->valorAnterior)->toBe('Técnica y datos')
        ->and($cambios[0]->valorNuevo)->toBe('Liderazgo')
        ->and($cambios[0]->recursoId)->toBe(2);
});

it('de sin área a un área deja el valor anterior en null', function () {
    $cambios = comparar(areaAhora: 4, nombreAhora: 'Desempeño');

    expect($cambios[0]->valorAnterior)->toBeNull()->and($cambios[0]->valorNuevo)->toBe('Desempeño');
});

it('sin diferencias devuelve una lista vacía y el orden de los ids no importa', function () {
    expect(comparar([1 => 'A', 2 => 'B'], [2 => 'B', 1 => 'A'], areaAntes: 1, nombreAntes: 'X', areaAhora: 1, nombreAhora: 'X'))->toBe([]);
});
