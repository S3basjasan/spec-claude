<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\CompetenciaMicrocontenidoModel;
use App\Modules\Desarrollo\Presentation\Facades\DesarrolloFacade;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;

it('devuelve solo los micro-contenidos Publicados vinculados, con su destino', function () {
    $competencia = CompetenciaModel::factory()->create();
    $publicado = microDe(['titulo' => 'Publicado', 'estado' => 'Publicado'], [$competencia->id]);
    microDe(['estado' => 'Borrador'], [$competencia->id]);
    microDe(['estado' => 'Archivado'], [$competencia->id]);
    microDe(['estado' => 'Publicado']);

    $lista = DesarrolloFacade::getMicrocontenidosPublicadosPorCompetencia($competencia->id);

    expect($lista)->toHaveCount(1)
        ->and($lista[0]['id'])->toBe($publicado->id)
        ->and($lista[0]['url_destino'])->toBe("/micro-learning/{$publicado->id}")
        ->and($lista[0]['codigo'])->toBe($publicado->codigo)
        ->and(array_keys($lista[0]))->toBe(['id', 'codigo', 'titulo', 'tipo', 'duracion_minutos', 'miniatura_url', 'url_destino']);
});

it('la oferta del Catálogo solo cuenta los micro-contenidos Publicados', function () {
    $competencia = CompetenciaModel::factory()->create();
    $borrador = microDe(['estado' => 'Borrador'], []);
    $archivado = microDe(['estado' => 'Archivado'], []);
    configurarFormativa($competencia->id, null, [], [$borrador->id, $archivado->id]);

    expect(DesarrolloFacade::competenciaTieneOferta($competencia->id))->toBeFalse()
        ->and(DesarrolloFacade::getOfertaPorCompetencia($competencia->id)['microcontenidos'])->toBe([]);

    $publicado = microDe(['estado' => 'Publicado'], []);
    CompetenciaMicrocontenidoModel::query()
        ->create(['competencia_id' => $competencia->id, 'microcontenido_id' => $publicado->id]);

    expect(DesarrolloFacade::competenciaTieneOferta($competencia->id))->toBeTrue()
        ->and(DesarrolloFacade::getOfertaPorCompetencia($competencia->id)['microcontenidos'])->toHaveCount(1);
});
