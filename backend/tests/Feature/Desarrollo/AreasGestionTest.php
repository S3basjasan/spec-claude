<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\AreaFormativaModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\HistorialFormativoModel;

it('crea un área formativa', function () {
    actuarComoAdmin();

    $this->postJson('/api/catalogo-formativo/areas', ['nombre' => '  Liderazgo   y gestión '])
        ->assertCreated()->assertJsonPath('data.nombre', 'Liderazgo y gestión');
});

it('rechaza un nombre vacío, solo espacios o demasiado largo', function () {
    actuarComoAdmin();

    $this->postJson('/api/catalogo-formativo/areas', [])->assertUnprocessable()->assertJsonValidationErrors('nombre');
    $this->postJson('/api/catalogo-formativo/areas', ['nombre' => '   '])->assertUnprocessable()->assertJsonValidationErrors('nombre');
    $this->postJson('/api/catalogo-formativo/areas', ['nombre' => str_repeat('a', 61)])->assertUnprocessable()->assertJsonValidationErrors('nombre');
});

it('rechaza un nombre duplicado sin distinguir mayúsculas ni espacios', function () {
    AreaFormativaModel::factory()->create(['nombre' => 'Técnica y datos']);
    actuarComoAdmin();

    $this->postJson('/api/catalogo-formativo/areas', ['nombre' => '  TÉCNICA   Y DATOS '])
        ->assertUnprocessable()->assertJsonValidationErrors('nombre');
    expect(AreaFormativaModel::query()->count())->toBe(1);
});

it('renombra un área y escribe el historial con competencia nula', function () {
    $area = AreaFormativaModel::factory()->create(['nombre' => 'Vieja']);
    actuarComoAdmin();

    $this->patchJson("/api/catalogo-formativo/areas/{$area->id}", ['nombre' => 'Nueva'])
        ->assertOk()->assertJsonPath('data.nombre', 'Nueva');

    $entrada = HistorialFormativoModel::query()->first();
    expect($entrada->competencia_id)->toBeNull()
        ->and($entrada->tipo_cambio)->toBe('AreaFormativa')
        ->and($entrada->accion)->toBe('Renombrado')
        ->and($entrada->valor_anterior)->toBe('Vieja')
        ->and($entrada->valor_nuevo)->toBe('Nueva')
        ->and($entrada->recurso_id)->toBe($area->id)
        ->and($entrada->user_id)->not->toBeNull();
});

it('permite cambiar solo la capitalización del propio nombre y rechaza el de otra área', function () {
    $area = AreaFormativaModel::factory()->create(['nombre' => 'liderazgo']);
    AreaFormativaModel::factory()->create(['nombre' => 'Otra']);
    actuarComoAdmin();

    $this->patchJson("/api/catalogo-formativo/areas/{$area->id}", ['nombre' => 'Liderazgo'])->assertOk()->assertJsonPath('data.nombre', 'Liderazgo');
    $this->patchJson("/api/catalogo-formativo/areas/{$area->id}", ['nombre' => 'OTRA'])->assertUnprocessable()->assertJsonValidationErrors('nombre');
});

it('renombrar sin cambio real no escribe historial', function () {
    $area = AreaFormativaModel::factory()->create(['nombre' => 'Igual']);
    actuarComoAdmin();

    $this->patchJson("/api/catalogo-formativo/areas/{$area->id}", ['nombre' => ' Igual '])->assertOk();

    expect(HistorialFormativoModel::query()->count())->toBe(0);
});

it('responde 404 al renombrar un área inexistente, 403 al colaborador y no existe DELETE', function () {
    actuarComoAdmin();
    $this->patchJson('/api/catalogo-formativo/areas/999999', ['nombre' => 'X'])->assertNotFound();

    $area = AreaFormativaModel::factory()->create();
    $this->deleteJson("/api/catalogo-formativo/areas/{$area->id}")->assertStatus(405);

    actuarComoUsuario();
    $this->postJson('/api/catalogo-formativo/areas', ['nombre' => 'Nueva'])->assertForbidden();
    $this->patchJson("/api/catalogo-formativo/areas/{$area->id}", ['nombre' => 'Nueva'])->assertForbidden();
});
