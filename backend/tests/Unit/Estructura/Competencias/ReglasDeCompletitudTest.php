<?php

use App\Modules\Estructura\Domain\Entities\Competencia;
use App\Modules\Estructura\Domain\Services\ReglasDeCompletitud;
use App\Modules\Estructura\Domain\ValueObjects\EstadoCompetencia;
use App\Modules\Estructura\Domain\ValueObjects\GradoDeCompetencia;
use App\Modules\Estructura\Domain\ValueObjects\TipoCompetencia;

/**
 * @param  array<int, array<int, string>>  $comportamientos  Textos por grado.
 * @param  array<int, string>  $preguntas
 * @param  array<int, string>  $descripciones
 */
function competenciaParaReglas(array $comportamientos, array $preguntas, array $descripciones = ['a', 'b', 'c', 'd']): Competencia
{
    $grados = [];
    foreach ($descripciones as $indice => $descripcion) {
        $grados[] = new GradoDeCompetencia($indice + 1, $indice + 1, chr(65 + $indice), 'Grado '.chr(65 + $indice), $descripcion, $comportamientos[$indice] ?? []);
    }

    return new Competencia(
        id: 1, codigo: 'COMP-0001', nombre: 'N', descripcion: 'd', tipo: TipoCompetencia::Cardinal,
        estado: EstadoCompetencia::Activo, version: 1, gradosDetalle: $grados, preguntas: $preguntas,
    );
}

function cuatroPorGrado(): array
{
    return [['1', '2', '3', '4'], ['1', '2', '3', '4'], ['1', '2', '3', '4'], ['1', '2', '3', '4']];
}

it('está completa con 4 grados, 16 comportamientos y 4 preguntas', function () {
    $competencia = competenciaParaReglas(cuatroPorGrado(), ['p1', 'p2', 'p3', 'p4']);

    expect(ReglasDeCompletitud::faltantes($competencia))->toBe([])
        ->and($competencia->estaCompleta())->toBeTrue();
});

it('indica los comportamientos cuando falta uno', function () {
    $comportamientos = cuatroPorGrado();
    array_pop($comportamientos[2]);

    expect(ReglasDeCompletitud::faltantes(competenciaParaReglas($comportamientos, ['p1', 'p2', 'p3', 'p4'])))->toBe(['comportamientos']);
});

it('indica las preguntas cuando falta una', function () {
    expect(ReglasDeCompletitud::faltantes(competenciaParaReglas(cuatroPorGrado(), ['p1', 'p2', 'p3'])))->toBe(['preguntas']);
});

it('un texto con solo espacios cuenta como vacío', function () {
    $comportamientos = cuatroPorGrado();
    $comportamientos[0][0] = '   ';

    expect(ReglasDeCompletitud::faltantes(competenciaParaReglas($comportamientos, ['p1', 'p2', 'p3', ' '], ['a', 'b', ' ', 'd'])))
        ->toBe(['grados', 'comportamientos', 'preguntas']);
});

it('indica los grados cuando no hay cuatro', function () {
    expect(ReglasDeCompletitud::faltantes(competenciaParaReglas(cuatroPorGrado(), ['p1', 'p2', 'p3', 'p4'], ['a', 'b', 'c'])))
        ->toContain('grados');
});
