<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use App\Modules\Estructura\Presentation\Facades\EstructuraFacade;

it('devuelve la competencia con las claves conservadas y las nuevas', function () {
    $competencia = CompetenciaModel::factory()->create(['nombre' => 'Liderazgo']);

    $dato = EstructuraFacade::getCompetencia($competencia->id);

    expect(array_keys($dato))->toBe(['id', 'codigo', 'nombre', 'descripcion', 'tipo', 'estado', 'grados', 'escala', 'grados_detalle', 'completa'])
        ->and($dato['nombre'])->toBe('Liderazgo')
        ->and(array_keys($dato['grados']))->toBe(['A', 'B', 'C', 'D'])
        ->and($dato['escala'])->toBe(['tipo_grado_id' => escalaId('Letras'), 'nombre' => 'Letras'])
        ->and($dato['completa'])->toBeTrue()
        ->and(array_column($dato['grados_detalle'], 'clave'))->toBe(['A', 'B', 'C', 'D'])
        ->and(array_column($dato['grados_detalle'], 'etiqueta'))->toBe(['Grado A', 'Grado B', 'Grado C', 'Grado D'])
        ->and($dato['grados_detalle'][0]['comportamientos'])->toHaveCount(4);
});

it('el id del grado de la fachada es el competencia_grado_id y la clave A–D no depende de la escala', function () {
    $competencia = CompetenciaModel::factory()->conEscala('Números')->create();

    $dato = EstructuraFacade::getCompetencia($competencia->id);

    expect(array_column($dato['grados_detalle'], 'etiqueta'))->toBe(['Nivel 1', 'Nivel 2', 'Nivel 3', 'Nivel 4'])
        ->and(array_column($dato['grados_detalle'], 'clave'))->toBe(['A', 'B', 'C', 'D'])
        ->and(array_keys($dato['grados']))->toBe(['A', 'B', 'C', 'D']);
});

it('devuelve null si la competencia no existe y consulta las inactivas', function () {
    $inactiva = CompetenciaModel::factory()->inactiva()->create();

    expect(EstructuraFacade::getCompetencia(99999))->toBeNull()
        ->and(EstructuraFacade::existeCompetencia($inactiva->id))->toBeTrue()
        ->and(EstructuraFacade::estaActiva($inactiva->id))->toBeFalse()
        ->and(EstructuraFacade::getCompetencia($inactiva->id)['estado'])->toBe('Inactivo');
});

it('lista solo las activas para nuevas asignaciones y todas las demás por ids', function () {
    $activa = CompetenciaModel::factory()->create(['nombre' => 'Activa']);
    $inactiva = CompetenciaModel::factory()->inactiva()->create(['nombre' => 'Retirada']);

    expect(array_column(EstructuraFacade::getCompetenciasActivas(), 'nombre'))->toBe(['Activa'])
        ->and(array_column(EstructuraFacade::getCompetenciasPorIds([$activa->id, $inactiva->id]), 'nombre'))->toBe(['Activa', 'Retirada'])
        ->and(EstructuraFacade::getCompetenciasPorIds([]))->toBe([]);
});

it('devuelve los comportamientos agrupados por grado', function () {
    $competencia = CompetenciaModel::factory()->create(['nombre' => 'Equipo']);

    $dato = EstructuraFacade::getComportamientos($competencia->id);

    expect($dato['competencia_id'])->toBe($competencia->id)
        ->and(array_keys($dato['comportamientos']))->toBe(['A', 'B', 'C', 'D'])
        ->and($dato['comportamientos']['A'])->toHaveCount(4)
        ->and($dato['completa'])->toBeTrue();
});

it('devuelve las cuatro preguntas en su orden', function () {
    $competencia = CompetenciaModel::factory()->create();

    $dato = EstructuraFacade::getPreguntas($competencia->id);

    expect($dato['preguntas'])->toHaveCount(4)
        ->and($dato['competencia_id'])->toBe($competencia->id);
});

it('devuelve null para comportamientos y preguntas si la competencia no tiene o no existe', function () {
    $incompleta = CompetenciaModel::factory()->incompleta()->create();

    expect(EstructuraFacade::getComportamientos($incompleta->id))->toBeNull()
        ->and(EstructuraFacade::getPreguntas($incompleta->id))->toBeNull()
        ->and(EstructuraFacade::getComportamientos(99999))->toBeNull()
        ->and(EstructuraFacade::getPreguntas(99999))->toBeNull();
});

it('conserva los comportamientos y preguntas de una competencia inactiva para los históricos', function () {
    $inactiva = CompetenciaModel::factory()->inactiva()->create();

    expect(EstructuraFacade::getComportamientos($inactiva->id)['estado'])->toBe('Inactivo')
        ->and(EstructuraFacade::getPreguntas($inactiva->id)['estado'])->toBe('Inactivo')
        ->and(array_column(EstructuraFacade::getComportamientosPorCompetencias([$inactiva->id]), 'competencia_id'))->toBe([$inactiva->id])
        ->and(array_column(EstructuraFacade::getPreguntasPorCompetencias([$inactiva->id]), 'competencia_id'))->toBe([$inactiva->id]);
});

it('solo permite asignar comportamientos y preguntas de competencias activas y completas', function () {
    $completa = CompetenciaModel::factory()->create(['nombre' => 'Completa']);
    $inactiva = CompetenciaModel::factory()->inactiva()->create(['nombre' => 'Retirada']);
    $incompleta = CompetenciaModel::factory()->incompleta()->create(['nombre' => 'Heredada']);

    expect(EstructuraFacade::tieneComportamientosActivos($completa->id))->toBeTrue()
        ->and(EstructuraFacade::tienePreguntasActivas($completa->id))->toBeTrue()
        ->and(EstructuraFacade::tieneComportamientosActivos($inactiva->id))->toBeFalse()
        ->and(EstructuraFacade::tienePreguntasActivas($inactiva->id))->toBeFalse()
        ->and(EstructuraFacade::tieneComportamientosActivos($incompleta->id))->toBeFalse()
        ->and(EstructuraFacade::tienePreguntasActivas($incompleta->id))->toBeFalse()
        ->and(array_column(EstructuraFacade::getComportamientosActivos(), 'nombre'))->toBe(['Completa'])
        ->and(array_column(EstructuraFacade::getPreguntasActivas(), 'nombre'))->toBe(['Completa']);
});
