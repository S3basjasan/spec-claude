<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\AreaFormativaModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\CursoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicrocontenidoModel;
use App\Modules\Desarrollo\Presentation\Facades\DesarrolloFacade;

it('getOfertaPorCompetencia devuelve solo recursos disponibles como arreglos', function () {
    $area = AreaFormativaModel::factory()->create(['nombre' => 'Técnica']);
    $curso = CursoModel::factory()->conDestino('/cursos/1')->create(['nombre' => 'Visible']);
    $archivado = CursoModel::factory()->archivado()->create();
    $micro = MicrocontenidoModel::factory()->create();
    configurarFormativa(20, $area->id, [$curso->id, $archivado->id], [$micro->id]);

    $oferta = DesarrolloFacade::getOfertaPorCompetencia(20);

    expect(array_keys($oferta))->toBe(['competencia_id', 'area_formativa', 'cursos', 'microcontenidos'])
        ->and($oferta['area_formativa'])->toBe(['id' => $area->id, 'nombre' => 'Técnica'])
        ->and(array_column($oferta['cursos'], 'nombre'))->toBe(['Visible'])
        ->and(array_keys($oferta['cursos'][0]))->toBe(['id', 'nombre', 'tipo', 'duracion_minutos', 'formador', 'url_destino'])
        ->and($oferta['microcontenidos'])->toHaveCount(1);
});

it('una competencia sin oferta devuelve arreglos vacíos y competenciaTieneOferta es false', function () {
    $oferta = DesarrolloFacade::getOfertaPorCompetencia(99);

    expect($oferta['cursos'])->toBe([])->and($oferta['microcontenidos'])->toBe([])->and($oferta['area_formativa'])->toBeNull()
        ->and(DesarrolloFacade::competenciaTieneOferta(99))->toBeFalse();
});

it('competenciaTieneOferta es true con al menos un recurso disponible y false con solo archivados', function () {
    configurarFormativa(1, null, [CursoModel::factory()->create()->id]);
    configurarFormativa(2, null, [CursoModel::factory()->archivado()->create()->id]);

    expect(DesarrolloFacade::competenciaTieneOferta(1))->toBeTrue()
        ->and(DesarrolloFacade::competenciaTieneOferta(2))->toBeFalse();
});
