<?php

use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\CategoriaDocumentalModel;

it('renombra conservando el id y registra quién y cuándo', function () {
    $admin = actuarComoAdminDeTenant();
    $categoria = CategoriaDocumentalModel::factory()->create(['nombre' => 'Contratos', 'updated_at' => '2024-01-01 10:00:00']);

    $respuesta = $this->patchJson(urlCategoria($categoria->id), ['nombre' => 'Contratos laborales', 'version' => 1])->assertOk();

    expect($respuesta->json('data.id'))->toBe($categoria->id)
        ->and($respuesta->json('data.nombre'))->toBe('Contratos laborales')
        ->and($respuesta->json('data.version'))->toBe(2);
    $fresca = $categoria->fresh();
    expect($fresca->updated_by)->toBe($admin->id)
        ->and($fresca->nombre_clave)->toBe('contratos laborales')
        ->and($fresca->nombre_busqueda)->toBe('contratos laborales')
        ->and($fresca->updated_at->format('Y-m-d'))->not->toBe('2024-01-01');
});

it('corrige el nombre de una categoría inactiva sin reactivarla', function () {
    actuarComoAdminDeTenant();
    $categoria = CategoriaDocumentalModel::factory()->inactiva()->create(['nombre' => 'Antigua']);

    $this->patchJson(urlCategoria($categoria->id), ['nombre' => 'Antigua corregida', 'version' => 1])->assertOk();

    expect($categoria->fresh()->estado)->toBe('Inactiva')->and($categoria->fresh()->nombre)->toBe('Antigua corregida');
});

it('rechaza un nombre que colisiona con otra categoría y conserva el previo', function () {
    actuarComoAdminDeTenant();
    CategoriaDocumentalModel::factory()->inactiva()->create(['nombre' => 'Certificados']);
    $categoria = CategoriaDocumentalModel::factory()->create(['nombre' => 'Contratos']);

    $this->patchJson(urlCategoria($categoria->id), ['nombre' => ' CERTIFICADOS ', 'version' => 1])->assertUnprocessable()
        ->assertJsonValidationErrors(['nombre']);

    expect($categoria->fresh()->nombre)->toBe('Contratos')->and($categoria->fresh()->version)->toBe(1);
});

it('cambiar solo mayúsculas o espacios de su propio nombre no es duplicado', function () {
    actuarComoAdminDeTenant();
    $categoria = CategoriaDocumentalModel::factory()->create(['nombre' => 'contratos']);

    $this->patchJson(urlCategoria($categoria->id), ['nombre' => '  Contratos ', 'version' => 1])->assertOk()->assertJsonPath('data.nombre', 'Contratos');
});

it('valida el nombre al editar', function () {
    actuarComoAdminDeTenant();
    $categoria = CategoriaDocumentalModel::factory()->create();

    $this->patchJson(urlCategoria($categoria->id), ['nombre' => '   ', 'version' => 1])->assertUnprocessable()->assertJsonValidationErrors(['nombre']);
    $this->patchJson(urlCategoria($categoria->id), ['nombre' => str_repeat('a', 101), 'version' => 1])->assertUnprocessable()->assertJsonValidationErrors(['nombre']);
    $this->patchJson(urlCategoria($categoria->id), ['nombre' => str_repeat('a', 100), 'version' => 1])->assertOk();
});

it('sin cambio real conserva versión y fecha de actualización', function () {
    actuarComoAdminDeTenant();
    $categoria = CategoriaDocumentalModel::factory()->create(['nombre' => 'Contratos', 'updated_at' => '2024-01-01 10:00:00']);

    $respuesta = $this->patchJson(urlCategoria($categoria->id), ['nombre' => ' Contratos ', 'version' => 1])->assertOk();

    expect($respuesta->json('data.version'))->toBe(1)
        ->and($categoria->fresh()->version)->toBe(1)
        ->and($categoria->fresh()->updated_at->format('Y-m-d H:i:s'))->toBe('2024-01-01 10:00:00');
});

it('responde 409 con versión obsoleta y conserva la primera escritura', function () {
    actuarComoAdminDeTenant();
    $categoria = CategoriaDocumentalModel::factory()->create(['nombre' => 'Contratos']);

    $this->patchJson(urlCategoria($categoria->id), ['nombre' => 'Primera', 'version' => 1])->assertOk();
    $this->patchJson(urlCategoria($categoria->id), ['nombre' => 'Segunda', 'version' => 1])->assertStatus(409)
        ->assertJsonPath('code', 'CATEGORIA_MODIFICADA');

    expect($categoria->fresh()->nombre)->toBe('Primera');
});

it('exige la versión y responde 404 de otro tenant', function () {
    actuarComoAdminDeTenant();
    $categoria = CategoriaDocumentalModel::factory()->create();
    $ajena = CategoriaDocumentalModel::factory()->create(['tenant_id' => 2]);

    $this->patchJson(urlCategoria($categoria->id), ['nombre' => 'X'])->assertUnprocessable()->assertJsonValidationErrors(['version']);
    $this->patchJson(urlCategoria($ajena->id), ['nombre' => 'X', 'version' => 1])->assertNotFound();
    $this->patchJson(urlCategoria(999999), ['nombre' => 'X', 'version' => 1])->assertNotFound();
    expect($ajena->fresh()->nombre)->not->toBe('X');
});

it('ignora el estado del cuerpo al editar el nombre y respeta la escritura deshabilitada', function () {
    actuarComoAdminDeTenant();
    $categoria = CategoriaDocumentalModel::factory()->create(['nombre' => 'Contratos']);

    $this->patchJson(urlCategoria($categoria->id), ['nombre' => 'Contratos 2', 'estado' => 'Inactiva', 'version' => 1])->assertOk();
    expect($categoria->fresh()->estado)->toBe('Activa');

    config(['hrms.gestion_personal.escritura_habilitada' => false]);
    $this->patchJson(urlCategoria($categoria->id), ['nombre' => 'Otra', 'version' => 2])->assertStatus(503);
});
