<?php

use App\Modules\Desarrollo\Domain\Exceptions\ImpactoInvalido;
use App\Modules\Desarrollo\Domain\Exceptions\ImpactoNoPermitido;
use App\Modules\Desarrollo\Domain\Services\ReglasDeImpacto;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoAccion;

function datosDeImpacto(array $cambios = []): array
{
    return $cambios + [
        'indicador' => 'Tiempo de cierre contable', 'unidad' => 'días', 'valor_antes' => 10, 'valor_despues' => 7,
        'beneficio_estimado' => 1500.5, 'justificacion' => 'Menos reprocesos tras la capacitación.',
    ];
}

function erroresDeImpacto(array $cambios): array
{
    try {
        (new ReglasDeImpacto)->validar(datosDeImpacto($cambios));
    } catch (ImpactoInvalido $e) {
        return $e->errores();
    }

    return [];
}

it('acepta una evaluación completa', function () {
    expect(erroresDeImpacto([]))->toBe([]);
});

it('solo permite evaluar acciones finalizadas', function (EstadoAccion $estado) {
    expect(fn () => (new ReglasDeImpacto)->validarAccion($estado))->toThrow(ImpactoNoPermitido::class);
})->with([EstadoAccion::Programada, EstadoAccion::EnCurso, EstadoAccion::Cancelada]);

it('permite evaluar una acción finalizada', function () {
    (new ReglasDeImpacto)->validarAccion(EstadoAccion::Finalizada);

    expect(true)->toBeTrue();
});

it('exige los campos de texto', function (string $campo) {
    expect(erroresDeImpacto([$campo => '  ']))->toHaveKey($campo)
        ->and(erroresDeImpacto([$campo => null]))->toHaveKey($campo);
})->with(['indicador', 'unidad', 'justificacion']);

it('exige los campos numéricos', function (string $campo) {
    expect(erroresDeImpacto([$campo => 'abc']))->toHaveKey($campo)
        ->and(erroresDeImpacto([$campo => null]))->toHaveKey($campo)
        ->and(erroresDeImpacto([$campo => '']))->toHaveKey($campo);
})->with(['valor_antes', 'valor_despues', 'beneficio_estimado']);

it('rechaza un beneficio negativo y acepta cero', function () {
    expect(erroresDeImpacto(['beneficio_estimado' => -1]))->toHaveKey('beneficio_estimado')
        ->and(erroresDeImpacto(['beneficio_estimado' => 0]))->toBe([]);
});

it('respeta las longitudes máximas', function () {
    expect(erroresDeImpacto(['indicador' => str_repeat('a', 151)]))->toHaveKey('indicador')
        ->and(erroresDeImpacto(['indicador' => str_repeat('a', 150)]))->toBe([])
        ->and(erroresDeImpacto(['unidad' => str_repeat('a', 51)]))->toHaveKey('unidad')
        ->and(erroresDeImpacto(['justificacion' => str_repeat('a', 2001)]))->toHaveKey('justificacion')
        ->and(erroresDeImpacto(['justificacion' => str_repeat('a', 2000)]))->toBe([]);
});

it('acepta valores antes y después negativos o decimales', function () {
    expect(erroresDeImpacto(['valor_antes' => -3.5, 'valor_despues' => '2.25']))->toBe([]);
});
