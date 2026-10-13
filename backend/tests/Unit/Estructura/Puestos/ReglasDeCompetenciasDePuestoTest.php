<?php

use App\Modules\Estructura\Domain\Exceptions\CompetenciasDePuestoInvalidas;
use App\Modules\Estructura\Domain\Services\ReglasDeCompetenciasDePuesto;
use App\Modules\Estructura\Domain\ValueObjects\AsignacionDeCompetencia;
use App\Modules\Estructura\Domain\ValueObjects\CompetenciaAsociada;
use App\Modules\Estructura\Domain\ValueObjects\CompetenciaConGrados;
use App\Modules\Estructura\Domain\ValueObjects\EstadoCompetencia;
use App\Modules\Estructura\Domain\ValueObjects\TipoCompetencia;

function competenciaConGrados(int $id, EstadoCompetencia $estado = EstadoCompetencia::Activo, array $grados = ['A', 'B', 'C', 'D']): CompetenciaConGrados
{
    return new CompetenciaConGrados(
        new CompetenciaAsociada($id, sprintf('COMP-%04d', $id), "Competencia $id", 'Descripción', TipoCompetencia::Cardinal, $estado),
        array_combine($grados, array_map(fn ($g) => "Grado $g", $grados)),
    );
}

function erroresDe(callable $accion): array
{
    try {
        $accion();
    } catch (CompetenciasDePuestoInvalidas $e) {
        return $e->errores();
    }

    return [];
}

beforeEach(function () {
    $this->reglas = new ReglasDeCompetenciasDePuesto;
});

it('acepta competencias activas con un grado que existe', function () {
    $this->reglas->validar(
        [new AsignacionDeCompetencia(1, 'A'), new AsignacionDeCompetencia(2, 'D')],
        [1 => competenciaConGrados(1), 2 => competenciaConGrados(2)],
    );

    expect(true)->toBeTrue();
});

it('rechaza una competencia inexistente con un error en su fila', function () {
    $errores = erroresDe(fn () => $this->reglas->validar([new AsignacionDeCompetencia(9, 'A')], []));

    expect($errores)->toBe(['competencias.0.competencia_id' => 'La competencia no existe o no está activa.']);
});

it('rechaza una competencia inactiva que el puesto no tenía', function () {
    $errores = erroresDe(fn () => $this->reglas->validar(
        [new AsignacionDeCompetencia(1, 'A')],
        [1 => competenciaConGrados(1, EstadoCompetencia::Inactivo)],
    ));

    expect($errores)->toBe(['competencias.0.competencia_id' => 'La competencia no existe o no está activa.']);
});

it('rechaza un grado que la competencia no define', function () {
    $errores = erroresDe(fn () => $this->reglas->validar(
        [new AsignacionDeCompetencia(1, 'D')],
        [1 => competenciaConGrados(1, grados: ['A', 'B', 'C'])],
    ));

    expect($errores)->toBe(['competencias.0.grado_requerido' => 'Ese grado no existe para la competencia.']);
});

it('devuelve los errores de todas las filas no válidas', function () {
    $errores = erroresDe(fn () => $this->reglas->validar(
        [new AsignacionDeCompetencia(1, 'A'), new AsignacionDeCompetencia(9, 'A'), new AsignacionDeCompetencia(2, 'D')],
        [1 => competenciaConGrados(1), 2 => competenciaConGrados(2, grados: ['A', 'B'])],
    ));

    expect(array_keys($errores))->toBe(['competencias.1.competencia_id', 'competencias.2.grado_requerido']);
});

it('el mensaje de la excepción es el del primer error', function () {
    try {
        $this->reglas->validar([new AsignacionDeCompetencia(9, 'A')], []);
    } catch (CompetenciasDePuestoInvalidas $e) {
        expect($e->getMessage())->toBe('La competencia no existe o no está activa.');

        return;
    }

    $this->fail('Debía lanzar CompetenciasDePuestoInvalidas.');
});
