<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use App\Modules\Estructura\Presentation\Facades\EstructuraFacade;

it('devuelve una competencia como arreglo con su estado y sus cuatro grados', function () {
    $competencia = CompetenciaModel::factory()->cardinal()->create(['nombre' => 'Liderazgo']);

    $datos = EstructuraFacade::getCompetencia($competencia->id);

    expect($datos)->toBeArray()
        ->and($datos['codigo'])->toBe('COMP-0001')
        ->and($datos['nombre'])->toBe('Liderazgo')
        ->and($datos['tipo'])->toBe('Cardinal')
        ->and($datos['estado'])->toBe('Activo')
        ->and(array_keys($datos['grados']))->toBe(['A', 'B', 'C', 'D']);
});

it('devuelve null si la competencia no existe', function () {
    expect(EstructuraFacade::getCompetencia(999))->toBeNull()
        ->and(EstructuraFacade::existeCompetencia(999))->toBeFalse()
        ->and(EstructuraFacade::estaActiva(999))->toBeFalse();
});

it('conserva las inactivas para consultar registros históricos', function () {
    $inactiva = CompetenciaModel::factory()->inactiva()->create(['nombre' => 'Retirada']);

    expect(EstructuraFacade::existeCompetencia($inactiva->id))->toBeTrue()
        ->and(EstructuraFacade::getCompetencia($inactiva->id)['estado'])->toBe('Inactivo')
        ->and(EstructuraFacade::getCompetenciasPorIds([$inactiva->id]))->toHaveCount(1);
});

it('solo una competencia activa es válida para nuevas asignaciones', function () {
    $activa = CompetenciaModel::factory()->create();
    $inactiva = CompetenciaModel::factory()->inactiva()->create();

    expect(EstructuraFacade::estaActiva($activa->id))->toBeTrue()
        ->and(EstructuraFacade::estaActiva($inactiva->id))->toBeFalse();
});

it('lista las activas ordenadas por nombre sin las inactivas', function () {
    CompetenciaModel::factory()->create(['nombre' => 'Beta']);
    CompetenciaModel::factory()->create(['nombre' => 'Alfa']);
    CompetenciaModel::factory()->inactiva()->create(['nombre' => 'Retirada']);

    $activas = EstructuraFacade::getCompetenciasActivas();

    expect(array_column($activas, 'nombre'))->toBe(['Alfa', 'Beta'])
        ->and($activas[0]['grados'])->toHaveCount(4);
});

it('devuelve solo las competencias que existen entre los ids dados', function () {
    $a = CompetenciaModel::factory()->create(['nombre' => 'Alfa']);
    $b = CompetenciaModel::factory()->create(['nombre' => 'Beta']);

    $resultado = EstructuraFacade::getCompetenciasPorIds([$b->id, $a->id, 999, $a->id]);

    expect($resultado)->toHaveCount(2)
        ->and(array_column($resultado, 'nombre'))->toBe(['Alfa', 'Beta'])
        ->and(EstructuraFacade::getCompetenciasPorIds([]))->toBe([]);
});

it('nunca expone modelos ni entidades, solo arreglos', function () {
    $competencia = CompetenciaModel::factory()->create();

    $datos = EstructuraFacade::getCompetencia($competencia->id);

    expect(array_filter($datos, 'is_object'))->toBe([]);
});

it('no ofrece registrar verificadores de uso: las competencias nunca se eliminan', function () {
    expect(method_exists(EstructuraFacade::getFacadeRoot(), 'registrarVerificadorDeUso'))->toBeFalse();
});
