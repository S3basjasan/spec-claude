<?php

use App\Modules\Desarrollo\Application\Ports\ExperienciaDelColaboradorInterface;
use App\Modules\Desarrollo\Application\Ports\PuestoActualDelColaboradorInterface;
use App\Modules\Desarrollo\Application\Ports\RutasParaAdecuacionInterface;
use App\Modules\Desarrollo\Application\Ports\ValoresDeLaPersonaInterface;
use App\Modules\Desarrollo\Application\Services\ArmadorDeDiagnostico;
use App\Modules\Desarrollo\Domain\Exceptions\ColaboradorNoElegible;
use App\Modules\Desarrollo\Domain\Exceptions\ObjetivoInvalido;
use App\Modules\Desarrollo\Domain\Exceptions\SinRequisitosEvaluables;
use App\Modules\Desarrollo\Domain\Services\CalculadorDeResultado;
use Tests\Support\Desarrollo\ColaboradoresVigentesFalso;
use Tests\Support\Desarrollo\PerfilesDePuestoFalsos;
use Tests\Support\Desarrollo\PuestosParaRutasFalso;

function perfilUnidad(int $id, string $nombre, array $competencias): array
{
    return ['puesto' => ['id' => $id, 'codigo' => "PU-{$id}", 'nombre' => $nombre], 'competencias' => array_map(
        fn (array $c) => ['competencia_id' => $c[0], 'codigo' => "COM-{$c[0]}", 'nombre' => "Competencia {$c[0]}", 'tipo' => 'Cardinal', 'activa' => $c[2] ?? true, 'nivel_requerido' => $c[1]],
        $competencias,
    )];
}

/** Arma el diagnóstico con puertos simulados, sin base de datos. */
function armadorUnidad(array $niveles = [1 => 'C', 2 => 'D'], ?float $anios = 3.0, array $inscripciones = []): ArmadorDeDiagnostico
{
    $perfiles = new PerfilesDePuestoFalsos([
        1 => perfilUnidad(1, 'Actual', [[1, 'C'], [2, 'D']]),
        2 => perfilUnidad(2, 'Objetivo', [[1, 'B'], [2, 'C'], [3, 'C', false]]),
        5 => perfilUnidad(5, 'Vacío', []),
    ]);

    return new ArmadorDeDiagnostico(
        new ColaboradoresVigentesFalso([10 => 'Ana']),
        new class($inscripciones) implements RutasParaAdecuacionInterface
        {
            public function __construct(private readonly array $inscripciones) {}

            public function inscripcionesActivas(int $tenantId, int $colaboradorId): array
            {
                return $this->inscripciones;
            }

            public function nivel(int $tenantId, int $nivelId): ?array
            {
                return null;
            }

            public function rutas(int $tenantId): array
            {
                return [];
            }
        },
        new PuestosParaRutasFalso([1 => ['codigo' => 'PU-1', 'nombre' => 'Actual', 'competencias' => []], 2 => ['codigo' => 'PU-2', 'nombre' => 'Objetivo', 'competencias' => []], 5 => ['codigo' => 'PU-5', 'nombre' => 'Vacío', 'competencias' => []]]),
        $perfiles,
        new class($niveles) implements ValoresDeLaPersonaInterface
        {
            public function __construct(private readonly array $niveles) {}

            public function nivelesDeCompetencias(int $tenantId, int $colaboradorId): array
            {
                return $this->niveles;
            }
        },
        new class($anios) implements ExperienciaDelColaboradorInterface
        {
            public function __construct(private readonly ?float $anios) {}

            public function aniosDe(int $tenantId, array $colaboradorIds): array
            {
                return [10 => $this->anios];
            }
        },
        new class implements PuestoActualDelColaboradorInterface
        {
            public function puestoActualDe(int $tenantId, array $colaboradorIds, string $fecha): array
            {
                return [10 => 1];
            }
        },
        new CalculadorDeResultado,
    );
}

it('calcula cada competencia activa con el requerido del puesto actual y omite las inactivas', function () {
    $d = armadorUnidad()->armar(1, 10, null, 2, null);

    expect(array_map(fn ($r) => [$r->elementoId, $r->requeridoActualNivel?->value, $r->requeridoObjetivoNivel?->value, $r->valorNivel?->value, $r->estado->value], $d->resultados))->toBe([
        [1, 'C', 'B', 'C', 'ConBrecha'],
        [2, 'D', 'C', 'D', 'ConBrecha'],
    ]);
    expect($d->puestoActualId)->toBe(1)->and($d->tipoCambio)->toBe('PuestoDirecto')
        ->and(array_column($d->avisos, 'tipo'))->toContain('inactivos_omitidos', 'conocimientos_desactivados', 'experiencia_no_evaluada');
});

it('agrega la experiencia solo cuando se indicó la experiencia requerida', function () {
    $sin = armadorUnidad()->armar(1, 10, null, 2, null);
    $con = armadorUnidad()->armar(1, 10, null, 2, 5.0);
    $sinDato = armadorUnidad(anios: null)->armar(1, 10, null, 2, 5.0);

    expect(array_map(fn ($r) => $r->dimension->value, $sin->resultados))->not->toContain('Experiencia');
    $exp = collect($con->resultados)->first(fn ($r) => $r->dimension->value === 'Experiencia');
    expect($exp->diferencia)->toBe(-2.0)->and($exp->estado->value)->toBe('ConBrecha')
        ->and(collect($sinDato->resultados)->first(fn ($r) => $r->dimension->value === 'Experiencia')->estado->value)->toBe('SinEvaluacion');
});

it('la competencia sin nivel registrado queda sin evaluación', function () {
    $d = armadorUnidad(niveles: [1 => 'B'])->armar(1, 10, null, 2, null);

    expect($d->resultados[0]->estado->value)->toBe('Cumple')->and($d->resultados[1]->estado->value)->toBe('SinEvaluacion')
        ->and(collect($d->avisos)->firstWhere('tipo', 'sin_evaluacion')['cantidad'])->toBe(1);
});

it('el tipo de cambio distingue promoción por ruta, puesto directo y mismo puesto', function () {
    $inscripcion = [['inscripcion_id' => 9, 'ruta_id' => 1, 'ruta_nombre' => 'R', 'nivel_actual' => null, 'nivel_objetivo' => ['id' => 100, 'orden' => 2, 'puesto_id' => 2, 'nombre' => 'N', 'experiencia_minima_anios' => 4.0]]];

    $porRuta = armadorUnidad(inscripciones: $inscripcion)->armar(1, 10, 9, null, null);
    $mismo = armadorUnidad()->armar(1, 10, null, 1, null);

    expect($porRuta->tipoCambio)->toBe('Promocion')->and($porRuta->nivelObjetivoId)->toBe(100)->and($porRuta->experienciaRequeridaAnios)->toBe(4.0)
        ->and($mismo->tipoCambio)->toBe('MismoPuesto')->and(array_column($mismo->avisos, 'tipo'))->toContain('mismo_puesto');
});

it('rechaza un objetivo no definido, ambiguo, un colaborador no vigente y un puesto sin requisitos', function () {
    expect(fn () => armadorUnidad()->armar(1, 10, null, null, null))->toThrow(ObjetivoInvalido::class);
    expect(fn () => armadorUnidad()->armar(1, 10, 9, 2, null))->toThrow(ObjetivoInvalido::class);
    expect(fn () => armadorUnidad()->armar(1, 99, null, 2, null))->toThrow(ColaboradorNoElegible::class);
    expect(fn () => armadorUnidad()->armar(1, 10, null, 5, null))->toThrow(SinRequisitosEvaluables::class);
});
