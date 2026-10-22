<?php

use App\Modules\Desarrollo\Domain\Services\CalculadorDeDiferencial;

function perfilDif(array $grados, ?float $experiencia = null): array
{
    $competencias = [];
    foreach ($grados as $id => $grado) {
        $competencias[] = ['competencia_id' => $id, 'codigo' => sprintf('COM-%03d', $id), 'nombre' => "Competencia {$id}", 'grado' => $grado];
    }

    return ['competencias' => $competencias, 'experiencia_anios' => $experiencia];
}

it('el nivel 1 no tiene diferencial', function () {
    $r = (new CalculadorDeDiferencial)->entre(null, perfilDif([1 => 'C', 2 => 'D'], 2.0));

    expect($r['competencias'])->toBe([1 => null, 2 => null])->and($r['quitadas'])->toBe([])->and($r['experiencia_anios'])->toBeNull();
});

it('marca nuevo, sube con antes y delta, baja e igual', function () {
    $r = (new CalculadorDeDiferencial)->entre(
        perfilDif([1 => 'C', 2 => 'B', 3 => 'A', 4 => 'C']),
        perfilDif([1 => 'A', 2 => 'B', 3 => 'C', 5 => 'D']),
    );

    expect($r['competencias'])->toBe([
        1 => ['tipo' => 'sube', 'antes' => 'C', 'delta' => 2],
        2 => ['tipo' => 'igual'],
        3 => ['tipo' => 'baja', 'antes' => 'A', 'delta' => -2],
        5 => ['tipo' => 'nuevo'],
    ]);
});

it('lista las competencias que el nivel anterior pedía y este ya no', function () {
    $r = (new CalculadorDeDiferencial)->entre(perfilDif([1 => 'C', 4 => 'B']), perfilDif([1 => 'C']));

    expect($r['quitadas'])->toBe([['competencia_id' => 4, 'codigo' => 'COM-004', 'nombre' => 'Competencia 4', 'grado_anterior' => 'B']]);
});

it('calcula los años adicionales de experiencia o null si falta alguno de los dos', function () {
    $calc = new CalculadorDeDiferencial;

    expect($calc->entre(perfilDif([], 1.0), perfilDif([], 3.5))['experiencia_anios'])->toBe(2.5)
        ->and($calc->entre(perfilDif([], null), perfilDif([], 3.0))['experiencia_anios'])->toBeNull()
        ->and($calc->entre(perfilDif([], 3.0), perfilDif([], null))['experiencia_anios'])->toBeNull();
});

it('no considera los requisitos de texto: el cálculo solo recibe competencias y experiencia', function () {
    $r = (new CalculadorDeDiferencial)->entre(
        ['competencias' => [], 'experiencia_anios' => null, 'requisitos' => ['formacion' => ['A']]],
        ['competencias' => [], 'experiencia_anios' => null, 'requisitos' => ['formacion' => ['B']]],
    );

    expect(array_keys($r))->toBe(['competencias', 'quitadas', 'experiencia_anios']);
});
