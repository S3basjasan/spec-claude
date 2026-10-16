<?php

use App\Modules\Desarrollo\Domain\Exceptions\AreaNoEncontrada;
use App\Modules\Desarrollo\Domain\Exceptions\CompetenciaInactiva;
use App\Modules\Desarrollo\Domain\Exceptions\RecursoNoDisponible;
use App\Modules\Desarrollo\Domain\Services\ReglasDeConfiguracionFormativa;
use App\Modules\Desarrollo\Domain\ValueObjects\DatosConfiguracion;

function validarConfiguracion(
    bool $activa,
    DatosConfiguracion $solicitado,
    array $cursosVinculados = [],
    array $microsVinculados = [],
    array $cursosDisponibles = [1, 2, 3],
    array $microsDisponibles = [1],
    array $areas = [1, 2],
): void {
    (new ReglasDeConfiguracionFormativa)->validar($activa, $solicitado, $cursosVinculados, $microsVinculados, $cursosDisponibles, $microsDisponibles, $areas);
}

it('acepta agregar un curso disponible en una competencia activa', function () {
    validarConfiguracion(true, new DatosConfiguracion(1, [1, 2], [1]));

    expect(true)->toBeTrue();
});

it('rechaza agregar recursos nuevos en una competencia inactiva', function () {
    validarConfiguracion(false, new DatosConfiguracion(null, [1], []));
})->throws(CompetenciaInactiva::class);

it('permite solo quitar recursos y cambiar el área en una competencia inactiva', function () {
    validarConfiguracion(false, new DatosConfiguracion(2, [1], []), cursosVinculados: [1, 2]);

    expect(true)->toBeTrue();
});

it('rechaza un recurso nuevo archivado o inexistente con la clave del índice', function () {
    try {
        validarConfiguracion(true, new DatosConfiguracion(null, [1, 99], []));
        $this->fail('Debía lanzar RecursoNoDisponible.');
    } catch (RecursoNoDisponible $e) {
        expect(array_keys($e->errores()))->toBe(['curso_ids.1']);
    }
});

it('permite conservar un recurso ya vinculado que se archivó', function () {
    validarConfiguracion(true, new DatosConfiguracion(null, [7], []), cursosVinculados: [7], cursosDisponibles: [1]);

    expect(true)->toBeTrue();
});

it('rechaza ids repetidos', function () {
    try {
        validarConfiguracion(true, new DatosConfiguracion(null, [1, 1], [1, 1]));
        $this->fail('Debía lanzar RecursoNoDisponible.');
    } catch (RecursoNoDisponible $e) {
        expect(array_keys($e->errores()))->toBe(['curso_ids.1', 'microcontenido_ids.1']);
    }
});

it('rechaza un área inexistente y acepta un área nula', function () {
    expect(fn () => validarConfiguracion(true, new DatosConfiguracion(99, [], [])))->toThrow(AreaNoEncontrada::class);

    validarConfiguracion(true, new DatosConfiguracion(null, [], []));
    expect(true)->toBeTrue();
});
