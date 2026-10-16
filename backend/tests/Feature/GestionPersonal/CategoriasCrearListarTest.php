<?php

use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\CategoriaDocumentalModel;

it('crea una categoría Activa con versión 1 y auditoría', function () {
    $admin = actuarComoAdminDeTenant();

    $respuesta = $this->postJson(URL_CATEGORIAS, ['nombre' => '  Contratos  '])->assertCreated();

    expect($respuesta->json('data.nombre'))->toBe('Contratos')
        ->and($respuesta->json('data.estado'))->toBe('Activa')
        ->and($respuesta->json('data.version'))->toBe(1)
        ->and($respuesta->json('data.created_by'))->toBe($admin->id)
        ->and($respuesta->json('data.updated_by'))->toBe($admin->id);
    expect(CategoriaDocumentalModel::query()->first()->tenant_id)->toBe(1);
});

it('rechaza nombre vacío, de espacios y de 101 caracteres; acepta 100', function () {
    actuarComoAdminDeTenant();

    $this->postJson(URL_CATEGORIAS, [])->assertUnprocessable()->assertJsonValidationErrors(['nombre']);
    $this->postJson(URL_CATEGORIAS, ['nombre' => '   '])->assertUnprocessable()->assertJsonValidationErrors(['nombre']);
    $this->postJson(URL_CATEGORIAS, ['nombre' => str_repeat('a', 101)])->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDACION')->assertJsonValidationErrors(['nombre']);
    $this->postJson(URL_CATEGORIAS, ['nombre' => str_repeat('a', 100)])->assertCreated();
    expect(CategoriaDocumentalModel::query()->count())->toBe(1);
});

it('rechaza un nombre repetido sin distinguir mayúsculas ni espacios, también contra una inactiva', function () {
    actuarComoAdminDeTenant();
    $this->postJson(URL_CATEGORIAS, ['nombre' => 'Contratos'])->assertCreated();
    CategoriaDocumentalModel::factory()->inactiva()->create(['nombre' => 'Certificados']);

    $this->postJson(URL_CATEGORIAS, ['nombre' => '  contratos '])->assertUnprocessable()
        ->assertJsonPath('errors.nombre.0', 'Ya existe una categoría con ese nombre.');
    $this->postJson(URL_CATEGORIAS, ['nombre' => 'CERTIFICADOS'])->assertUnprocessable()->assertJsonValidationErrors(['nombre']);
    expect(CategoriaDocumentalModel::query()->count())->toBe(2);
});

it('conserva las tildes en la unicidad', function () {
    actuarComoAdminDeTenant();

    $this->postJson(URL_CATEGORIAS, ['nombre' => 'Educación'])->assertCreated();
    $this->postJson(URL_CATEGORIAS, ['nombre' => 'educación'])->assertUnprocessable();
    $this->postJson(URL_CATEGORIAS, ['nombre' => 'Educacion'])->assertCreated();
});

it('permite el mismo nombre en tenants distintos y no mezcla los catálogos', function () {
    actuarComoAdminDeTenant();
    CategoriaDocumentalModel::factory()->create(['tenant_id' => 2, 'nombre' => 'Contratos']);

    $this->postJson(URL_CATEGORIAS, ['nombre' => 'Contratos'])->assertCreated();

    $nombres = array_column($this->getJson(URL_CATEGORIAS.'?estado=Todas')->json('data'), 'nombre');
    expect($nombres)->toBe(['Contratos'])->and(CategoriaDocumentalModel::query()->count())->toBe(2);
});

it('ignora tenant_id, created_by y estado del cuerpo', function () {
    $admin = actuarComoAdminDeTenant();

    $this->postJson(URL_CATEGORIAS, ['nombre' => 'Contratos', 'tenant_id' => 2, 'created_by' => 999, 'estado' => 'Inactiva', 'id' => 77])->assertCreated();

    $fila = CategoriaDocumentalModel::query()->first();
    expect($fila->tenant_id)->toBe(1)->and($fila->created_by)->toBe($admin->id)->and($fila->estado)->toBe('Activa')->and($fila->id)->not->toBe(77);
});

it('lista por defecto solo las activas ordenadas por nombre', function () {
    actuarComoAdminDeTenant();
    CategoriaDocumentalModel::factory()->create(['nombre' => 'Zeta']);
    CategoriaDocumentalModel::factory()->create(['nombre' => 'Álbum']);
    CategoriaDocumentalModel::factory()->create(['nombre' => 'Beta']);
    CategoriaDocumentalModel::factory()->inactiva()->create(['nombre' => 'Antigua']);

    $nombres = fn (string $consulta = '') => array_column($this->getJson(URL_CATEGORIAS.$consulta)->assertOk()->json('data'), 'nombre');

    expect($nombres())->toBe(['Álbum', 'Beta', 'Zeta'])
        ->and($nombres('?estado=Inactiva'))->toBe(['Antigua'])
        ->and($nombres('?estado=Todas'))->toBe(['Álbum', 'Antigua', 'Beta', 'Zeta']);
});

it('busca de forma parcial sin tildes ni mayúsculas y trata % y _ como texto', function () {
    actuarComoAdminDeTenant();
    CategoriaDocumentalModel::factory()->create(['nombre' => 'Educación formal']);
    CategoriaDocumentalModel::factory()->create(['nombre' => 'Contratos']);
    CategoriaDocumentalModel::factory()->create(['nombre' => '100% cumplido']);
    CategoriaDocumentalModel::factory()->create(['nombre' => 'a_b']);
    CategoriaDocumentalModel::factory()->create(['nombre' => 'axb']);

    $buscar = fn (string $q) => array_column($this->getJson(URL_CATEGORIAS.'?q='.urlencode($q))->assertOk()->json('data'), 'nombre');

    expect($buscar('EDUCACION'))->toBe(['Educación formal'])
        ->and($buscar('trat'))->toBe(['Contratos'])
        ->and($buscar('%'))->toBe(['100% cumplido'])
        ->and($buscar('a_b'))->toBe(['a_b'])
        ->and($buscar('zzzz'))->toBe([]);
});

it('pagina y valida los parámetros del listado', function () {
    actuarComoAdminDeTenant();
    CategoriaDocumentalModel::factory()->count(5)->create();

    $pagina = $this->getJson(URL_CATEGORIAS.'?per_page=2&page=3')->assertOk();

    expect($pagina->json('data'))->toHaveCount(1)
        ->and($pagina->json('meta.total'))->toBe(5)
        ->and($pagina->json('meta.last_page'))->toBe(3);
    $this->getJson(URL_CATEGORIAS.'?per_page=101')->assertUnprocessable();
    $this->getJson(URL_CATEGORIAS.'?per_page=0')->assertUnprocessable();
    $this->getJson(URL_CATEGORIAS.'?page=0')->assertUnprocessable();
    $this->getJson(URL_CATEGORIAS.'?estado=Otra')->assertUnprocessable();
    $this->getJson(URL_CATEGORIAS.'?per_page=100')->assertOk();
});

it('consulta una categoría y responde 404 idéntico para inexistente y de otro tenant', function () {
    actuarComoAdminDeTenant();
    $propia = CategoriaDocumentalModel::factory()->create(['nombre' => 'Contratos']);
    $ajena = CategoriaDocumentalModel::factory()->create(['tenant_id' => 2]);

    $this->getJson(URL_CATEGORIAS."/{$propia->id}")->assertOk()->assertJsonPath('data.nombre', 'Contratos');
    $a = $this->getJson(URL_CATEGORIAS.'/999999')->assertNotFound();
    $b = $this->getJson(URL_CATEGORIAS."/{$ajena->id}")->assertNotFound();

    expect($b->json())->toBe($a->json())->and($a->json('code'))->toBe('CATEGORIA_NO_ENCONTRADA');
});

it('exige sesión y deniega al colaborador en todas las rutas sin revelar existencia', function () {
    $existente = CategoriaDocumentalModel::factory()->create();

    $this->getJson(URL_CATEGORIAS)->assertUnauthorized();
    $this->postJson(URL_CATEGORIAS, ['nombre' => 'X'])->assertUnauthorized();

    actuarComoUsuarioDeTenant();
    $this->getJson(URL_CATEGORIAS)->assertForbidden();
    $this->getJson(URL_CATEGORIAS."/{$existente->id}")->assertForbidden();
    $this->getJson(URL_CATEGORIAS.'/999999')->assertForbidden();
    $this->postJson(URL_CATEGORIAS, ['nombre' => 'X'])->assertForbidden();
    $this->patchJson(URL_CATEGORIAS."/{$existente->id}", ['nombre' => 'X', 'version' => 1])->assertForbidden();
    $this->patchJson(URL_CATEGORIAS."/{$existente->id}/estado", ['estado' => 'Inactiva', 'version' => 1])->assertForbidden();
    expect(CategoriaDocumentalModel::query()->count())->toBe(1)->and($existente->fresh()->estado)->toBe('Activa');
});

it('no existe eliminación', function () {
    actuarComoAdminDeTenant();
    $categoria = CategoriaDocumentalModel::factory()->create();

    expect($this->deleteJson(URL_CATEGORIAS."/{$categoria->id}")->status())->toBeIn([404, 405])
        ->and(CategoriaDocumentalModel::query()->count())->toBe(1);
});

it('respeta la escritura deshabilitada', function () {
    actuarComoAdminDeTenant();
    config(['hrms.gestion_personal.escritura_habilitada' => false]);

    $this->postJson(URL_CATEGORIAS, ['nombre' => 'Contratos'])->assertStatus(503)->assertJsonPath('code', 'ESCRITURA_DESHABILITADA');
    $this->getJson(URL_CATEGORIAS)->assertOk();
});
