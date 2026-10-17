<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\AreaFormativaModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\CompetenciaCursoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\CompetenciaFormativaModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\CursoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\HistorialFormativoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicrocontenidoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;

it('asocia cursos y micro-contenidos, sube la versión y actualiza el detalle', function () {
    $competencia = CompetenciaModel::factory()->create();
    [$c1, $c2] = [CursoModel::factory()->create(), CursoModel::factory()->create()];
    $micro = MicrocontenidoModel::factory()->publicado()->create();
    actuarComoAdmin();

    $respuesta = $this->putJson("/api/catalogo-formativo/{$competencia->id}", cuerpoFormativo([
        'curso_ids' => [$c1->id, $c2->id],
        'microcontenido_ids' => [$micro->id],
    ]))->assertOk();

    expect($respuesta->json('data.version'))->toBe(1)
        ->and(array_column($respuesta->json('data.cursos'), 'id'))->toBe([$c1->id, $c2->id])
        ->and($respuesta->json('data.microcontenidos.0.id'))->toBe($micro->id)
        ->and($respuesta->json('data.ultima_configuracion.usuario'))->not->toBeNull();

    $lista = $this->getJson('/api/catalogo-formativo')->json('data.0');
    expect($lista['cursos_count'])->toBe(2)->and($lista['microcontenidos_count'])->toBe(1);
});

it('quitar un vínculo no elimina el curso y un curso puede estar en varias competencias', function () {
    [$a, $b] = [CompetenciaModel::factory()->create(), CompetenciaModel::factory()->create()];
    $curso = CursoModel::factory()->create();
    configurarFormativa($a->id, null, [$curso->id]);
    actuarComoAdmin();

    $this->putJson("/api/catalogo-formativo/{$b->id}", cuerpoFormativo(['curso_ids' => [$curso->id]]))->assertOk();
    $this->putJson("/api/catalogo-formativo/{$a->id}", cuerpoFormativo(['version' => 1]))->assertOk();

    expect(CursoModel::query()->whereKey($curso->id)->exists())->toBeTrue()
        ->and(CompetenciaCursoModel::query()->where('competencia_id', $a->id)->count())->toBe(0)
        ->and(CompetenciaCursoModel::query()->where('competencia_id', $b->id)->count())->toBe(1);
});

it('rechaza ids repetidos e inexistentes con la clave del índice', function () {
    $competencia = CompetenciaModel::factory()->create();
    $curso = CursoModel::factory()->create();
    actuarComoAdmin();

    $this->putJson("/api/catalogo-formativo/{$competencia->id}", cuerpoFormativo(['curso_ids' => [$curso->id, $curso->id]]))
        ->assertUnprocessable()->assertJsonValidationErrors('curso_ids.1');
    $this->putJson("/api/catalogo-formativo/{$competencia->id}", cuerpoFormativo(['curso_ids' => [999999]]))
        ->assertUnprocessable()->assertJsonValidationErrors('curso_ids.0');
    expect(CompetenciaCursoModel::query()->count())->toBe(0);
});

it('rechaza un recurso nuevo archivado', function () {
    $competencia = CompetenciaModel::factory()->create();
    $archivado = CursoModel::factory()->archivado()->create();
    actuarComoAdmin();

    $this->putJson("/api/catalogo-formativo/{$competencia->id}", cuerpoFormativo(['curso_ids' => [$archivado->id]]))
        ->assertUnprocessable()->assertJsonValidationErrors('curso_ids.0');
});

it('permite conservar un recurso ya vinculado que se archivó', function () {
    $competencia = CompetenciaModel::factory()->create();
    $curso = CursoModel::factory()->create();
    configurarFormativa($competencia->id, null, [$curso->id]);
    $curso->update(['estado' => 'Archivado']);
    actuarComoAdmin();

    $this->putJson("/api/catalogo-formativo/{$competencia->id}", cuerpoFormativo(['curso_ids' => [$curso->id], 'version' => 1]))->assertOk();

    expect(HistorialFormativoModel::query()->count())->toBe(0);
});

it('en una competencia inactiva rechaza recursos nuevos pero permite quitar y cambiar el área', function () {
    $inactiva = CompetenciaModel::factory()->inactiva()->create();
    $curso = CursoModel::factory()->create();
    $otro = CursoModel::factory()->create();
    $area = AreaFormativaModel::factory()->create();
    configurarFormativa($inactiva->id, null, [$curso->id]);
    actuarComoAdmin();

    $this->putJson("/api/catalogo-formativo/{$inactiva->id}", cuerpoFormativo(['curso_ids' => [$curso->id, $otro->id], 'version' => 1]))
        ->assertUnprocessable()->assertJsonValidationErrors('competencia');

    $this->putJson("/api/catalogo-formativo/{$inactiva->id}", cuerpoFormativo(['area_formativa_id' => $area->id, 'curso_ids' => [], 'version' => 1]))
        ->assertOk()->assertJsonPath('data.cursos', []);
});

it('un guardado sin cambios no modifica la versión ni escribe historial', function () {
    $competencia = CompetenciaModel::factory()->create();
    $curso = CursoModel::factory()->create();
    configurarFormativa($competencia->id, null, [$curso->id]);
    actuarComoAdmin();

    $this->putJson("/api/catalogo-formativo/{$competencia->id}", cuerpoFormativo(['curso_ids' => [$curso->id], 'version' => 1]))
        ->assertOk()->assertJsonPath('data.version', 1);

    expect(HistorialFormativoModel::query()->count())->toBe(0)
        ->and(CompetenciaFormativaModel::query()->first()->version)->toBe(1);
});

it('una versión desactualizada responde 409 y no guarda nada', function () {
    $competencia = CompetenciaModel::factory()->create();
    $curso = CursoModel::factory()->create();
    configurarFormativa($competencia->id, null, [], [], 3);
    actuarComoAdmin();

    $this->putJson("/api/catalogo-formativo/{$competencia->id}", cuerpoFormativo(['curso_ids' => [$curso->id], 'version' => 1]))
        ->assertStatus(409);

    expect(CompetenciaCursoModel::query()->count())->toBe(0);
});

it('responde 404 si la competencia no existe, 403 al colaborador y 401 sin sesión', function () {
    $competencia = CompetenciaModel::factory()->create();

    $this->putJson("/api/catalogo-formativo/{$competencia->id}", cuerpoFormativo())->assertUnauthorized();

    actuarComoUsuario();
    $this->putJson("/api/catalogo-formativo/{$competencia->id}", cuerpoFormativo())->assertForbidden();

    actuarComoAdmin();
    $this->putJson('/api/catalogo-formativo/999999', cuerpoFormativo())->assertNotFound();
});

it('valida la forma de la solicitud', function () {
    $competencia = CompetenciaModel::factory()->create();
    actuarComoAdmin();

    $this->putJson("/api/catalogo-formativo/{$competencia->id}", [])->assertUnprocessable()
        ->assertJsonValidationErrors(['area_formativa_id', 'curso_ids', 'microcontenido_ids', 'version']);
});

it('cambia el área, escribe el historial con los nombres y rechaza un área inexistente', function () {
    $competencia = CompetenciaModel::factory()->create();
    $vieja = AreaFormativaModel::factory()->create(['nombre' => 'Técnica']);
    $nueva = AreaFormativaModel::factory()->create(['nombre' => 'Liderazgo']);
    configurarFormativa($competencia->id, $vieja->id);
    actuarComoAdmin();

    $this->putJson("/api/catalogo-formativo/{$competencia->id}", cuerpoFormativo(['area_formativa_id' => 999999, 'version' => 1]))
        ->assertUnprocessable()->assertJsonValidationErrors('area_formativa_id');

    $this->putJson("/api/catalogo-formativo/{$competencia->id}", cuerpoFormativo(['area_formativa_id' => $nueva->id, 'version' => 1]))
        ->assertOk()->assertJsonPath('data.area_formativa.nombre', 'Liderazgo');

    $entrada = HistorialFormativoModel::query()->first();
    expect($entrada->tipo_cambio)->toBe('AreaFormativa')
        ->and($entrada->accion)->toBe('Cambiado')
        ->and($entrada->valor_anterior)->toBe('Técnica')
        ->and($entrada->valor_nuevo)->toBe('Liderazgo');

    $this->putJson("/api/catalogo-formativo/{$competencia->id}", cuerpoFormativo(['area_formativa_id' => null, 'version' => 2]))->assertOk();
    expect(HistorialFormativoModel::query()->latest('id')->first()->valor_nuevo)->toBeNull();
});
