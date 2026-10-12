<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use App\Modules\Estructura\Presentation\Facades\EstructuraFacade;

it('devuelve las preguntas de una competencia activa e inactiva (históricos)', function () {
    $activa = registroPreguntasDe(['nombre' => 'Activa']);
    $inactiva = registroPreguntasDe(['nombre' => 'Retirada', 'estado' => 'Inactivo']);

    $a = EstructuraFacade::getPreguntas($activa->id);
    $i = EstructuraFacade::getPreguntas($inactiva->id);

    expect($a['nombre'])->toBe('Activa')
        ->and($a['preguntas'])->toHaveCount(4)
        ->and($i['estado'])->toBe('Inactivo')
        ->and($i['preguntas'])->toBe(array_values($i['preguntas']));
});

it('devuelve null para una competencia sin registro o inexistente', function () {
    $sin = CompetenciaModel::factory()->create();

    expect(EstructuraFacade::getPreguntas($sin->id))->toBeNull()
        ->and(EstructuraFacade::getPreguntas(999))->toBeNull();
});

it('consulta varias competencias a la vez, incluidas las inactivas, y omite las que no tienen registro', function () {
    $uno = registroPreguntasDe();
    $dos = registroPreguntasDe(['estado' => 'Inactivo']);
    $sin = CompetenciaModel::factory()->create();

    expect(EstructuraFacade::getPreguntasPorCompetencias([$uno->id, $dos->id, $sin->id, $uno->id]))->toHaveCount(2)
        ->and(EstructuraFacade::getPreguntasPorCompetencias([]))->toBe([]);
});

it('solo las competencias activas con registro sirven para nuevas asignaciones', function () {
    $activa = registroPreguntasDe(['nombre' => 'B activa']);
    registroPreguntasDe(['nombre' => 'Retirada', 'estado' => 'Inactivo']);
    $sinRegistro = CompetenciaModel::factory()->create();
    $otraActiva = registroPreguntasDe(['nombre' => 'A activa']);

    expect(EstructuraFacade::tienePreguntasActivas($activa->id))->toBeTrue()
        ->and(EstructuraFacade::tienePreguntasActivas($sinRegistro->id))->toBeFalse()
        ->and(array_column(EstructuraFacade::getPreguntasActivas(), 'nombre'))->toBe(['A activa', 'B activa'])
        ->and(EstructuraFacade::getPreguntasActivas()[0]['competencia_id'])->toBe($otraActiva->id);
});

it('al desactivar la competencia deja de ser válida para nuevas asignaciones pero sigue consultable', function () {
    $competencia = registroPreguntasDe();
    actuarComoAdmin();

    $this->patchJson("/api/competencias/{$competencia->id}/estado", ['estado' => 'Inactivo'])->assertOk();

    expect(EstructuraFacade::tienePreguntasActivas($competencia->id))->toBeFalse()
        ->and(EstructuraFacade::getPreguntas($competencia->id)['estado'])->toBe('Inactivo');

    $this->patchJson("/api/competencias/{$competencia->id}/estado", ['estado' => 'Activo'])->assertOk();
    expect(EstructuraFacade::tienePreguntasActivas($competencia->id))->toBeTrue();
});
