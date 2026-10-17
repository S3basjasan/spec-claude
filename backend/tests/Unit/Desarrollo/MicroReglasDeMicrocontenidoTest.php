<?php

use App\Modules\Desarrollo\Domain\Exceptions\MicroNoPublicable;
use App\Modules\Desarrollo\Domain\Exceptions\TipoInmutable;
use App\Modules\Desarrollo\Domain\Exceptions\TransicionMicroInvalida;
use App\Modules\Desarrollo\Domain\Services\ReglasDeMicrocontenido;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoMicro;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoMicro;

it('permite solo Borrador a Publicado, Publicado a Archivado y Archivado a Publicado', function (EstadoMicro $desde, EstadoMicro $hasta) {
    (new ReglasDeMicrocontenido)->validarTransicion($desde, $hasta);

    expect(true)->toBeTrue();
})->with([
    [EstadoMicro::Borrador, EstadoMicro::Publicado],
    [EstadoMicro::Publicado, EstadoMicro::Archivado],
    [EstadoMicro::Archivado, EstadoMicro::Publicado],
]);

it('rechaza las demás transiciones y repetir el estado', function (EstadoMicro $desde, EstadoMicro $hasta) {
    expect(fn () => (new ReglasDeMicrocontenido)->validarTransicion($desde, $hasta))->toThrow(TransicionMicroInvalida::class);
})->with([
    [EstadoMicro::Borrador, EstadoMicro::Archivado],
    [EstadoMicro::Borrador, EstadoMicro::Borrador],
    [EstadoMicro::Publicado, EstadoMicro::Borrador],
    [EstadoMicro::Archivado, EstadoMicro::Borrador],
    [EstadoMicro::Publicado, EstadoMicro::Publicado],
]);

it('un video completo con competencia activa es publicable', function () {
    $reglas = new ReglasDeMicrocontenido;

    expect($reglas->faltantes('Guía', 10, TipoMicro::Video, 'https://x.test', 0, 1))->toBe([]);
});

it('lista los datos que faltan para publicar en un orden estable', function () {
    $reglas = new ReglasDeMicrocontenido;

    expect($reglas->faltantes('  ', 0, TipoMicro::Pdf, null, 0, 0))->toBe(['titulo', 'duracion', 'url', 'competencias'])
        ->and($reglas->faltantes('Quiz', 5, TipoMicro::Quiz, null, 0, 1))->toBe(['preguntas'])
        ->and($reglas->faltantes('Quiz', 5, TipoMicro::Quiz, null, 2, 1))->toBe([]);
});

it('validarPublicable lanza con la lista de faltantes', function () {
    try {
        (new ReglasDeMicrocontenido)->validarPublicable('Guía', 5, TipoMicro::Articulo, null, 0, 0);
        $this->fail('Debió lanzar MicroNoPublicable');
    } catch (MicroNoPublicable $e) {
        expect($e->faltantes)->toBe(['url', 'competencias'])->and($e->codigoError())->toBe('faltan_requisitos');
    }
});

it('el tipo no se puede cambiar', function () {
    $reglas = new ReglasDeMicrocontenido;

    $reglas->validarTipoInmutable(TipoMicro::Video, TipoMicro::Video);

    expect(fn () => $reglas->validarTipoInmutable(TipoMicro::Video, TipoMicro::Quiz))->toThrow(TipoInmutable::class);
});
