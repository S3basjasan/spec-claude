<?php

use App\Modules\Desarrollo\Domain\Entities\ParametrosDeAdecuacion;
use App\Modules\Desarrollo\Domain\Exceptions\PesosInvalidos;
use App\Modules\Desarrollo\Domain\Exceptions\UmbralInvalido;
use App\Modules\Desarrollo\Domain\Services\ReglasDeParametrosDeAdecuacion;
use App\Modules\Desarrollo\Domain\ValueObjects\ClaveDePermisoAdecuacion;
use App\Modules\Desarrollo\Domain\ValueObjects\CodigoDeAdecuacion;
use App\Modules\Desarrollo\Domain\ValueObjects\DimensionDeAdecuacion;
use App\Modules\Desarrollo\Domain\ValueObjects\PesosPorDimension;

it('el código tiene el formato ADE-0001 y es secuencial', function () {
    expect(CodigoDeAdecuacion::desdeNumero(1)->valor())->toBe('ADE-0001')
        ->and(CodigoDeAdecuacion::desdeNumero(1)->siguiente()->valor())->toBe('ADE-0002')
        ->and(CodigoDeAdecuacion::desdeNumero(12345)->valor())->toBe('ADE-12345')
        ->and(CodigoDeAdecuacion::desdeTexto('ADE-0042')->numero)->toBe(42);
    expect(fn () => CodigoDeAdecuacion::desdeNumero(0))->toThrow(InvalidArgumentException::class);
    expect(fn () => CodigoDeAdecuacion::desdeTexto('ADE-1'))->toThrow(InvalidArgumentException::class);
});

it('los pesos deben sumar 100 y no pueden ser negativos', function () {
    expect(fn () => new PesosPorDimension(40, 30, 20))->toThrow(PesosInvalidos::class);
    expect(fn () => new PesosPorDimension(-10, 60, 50))->toThrow(PesosInvalidos::class);
    expect((new PesosPorDimension(40, 30, 30))->comoArreglo())->toBe(['Competencia' => 40, 'Conocimiento' => 30, 'Experiencia' => 30]);
});

it('reescala los pesos de las dimensiones presentes', function () {
    $r = (new PesosPorDimension(40, 30, 30))->reescalar([DimensionDeAdecuacion::Competencia, DimensionDeAdecuacion::Experiencia]);

    expect(round($r['Competencia'], 4))->toBe(round(4 / 7, 4))
        ->and(round($r['Experiencia'], 4))->toBe(round(3 / 7, 4))
        ->and((new PesosPorDimension(40, 30, 30))->reescalar([]))->toBe([]);
});

it('valida los parámetros: enteros, suma 100 y umbral de al menos 1', function () {
    $reglas = new ReglasDeParametrosDeAdecuacion;
    $ok = $reglas->validar(['competencias' => 50, 'conocimientos' => 25, 'experiencia' => 25], 6);

    expect($ok->umbralMeses)->toBe(6)->and($ok->pesos->competencias)->toBe(50);
    expect(fn () => $reglas->validar(['competencias' => 50, 'conocimientos' => 25, 'experiencia' => 15], 6))->toThrow(PesosInvalidos::class);
    expect(fn () => $reglas->validar(['competencias' => 50.5, 'conocimientos' => 24.5, 'experiencia' => 25], 6))->toThrow(PesosInvalidos::class);
    expect(fn () => $reglas->validar(['competencias' => 50, 'conocimientos' => 25, 'experiencia' => 25], 0))->toThrow(UmbralInvalido::class);
    expect(fn () => $reglas->validar(['competencias' => 50, 'conocimientos' => 25, 'experiencia' => 25], '6'))->toThrow(UmbralInvalido::class);
});

it('detecta solo los campos que cambian', function () {
    $reglas = new ReglasDeParametrosDeAdecuacion;
    $nuevo = $reglas->validar(['competencias' => 50, 'conocimientos' => 30, 'experiencia' => 20], 6);

    expect($reglas->cambios(ParametrosDeAdecuacion::iniciales(), $nuevo))->toBe([
        ['campo' => 'peso_competencias', 'anterior' => '40', 'nuevo' => '50'],
        ['campo' => 'peso_experiencia', 'anterior' => '30', 'nuevo' => '20'],
        ['campo' => 'umbral_meses', 'anterior' => '12', 'nuevo' => '6'],
    ]);
    expect($reglas->cambios(ParametrosDeAdecuacion::iniciales(), ParametrosDeAdecuacion::iniciales()))->toBe([]);
});

it('las claves de permiso son estables y el administrador no recibe ver_propio', function () {
    expect(array_map(fn ($c) => $c->value, ClaveDePermisoAdecuacion::cases()))->toBe([
        'desarrollo.adecuacion.generar', 'desarrollo.adecuacion.ver', 'desarrollo.adecuacion.comparar',
        'desarrollo.adecuacion.mapa', 'desarrollo.adecuacion.parametros', 'desarrollo.adecuacion.ver_propio',
    ]);
    expect(ClaveDePermisoAdecuacion::deAdministrador())->not->toContain(ClaveDePermisoAdecuacion::VerPropio)
        ->and(ClaveDePermisoAdecuacion::deColaborador())->toBe([ClaveDePermisoAdecuacion::VerPropio]);
});
