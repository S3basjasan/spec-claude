<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\UnidadModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Laravel\Sanctum\Sanctum;

it('rechaza con 401 sin sesión', function () {
    $this->getJson('/api/unidades')->assertUnauthorized();
    $this->getJson('/api/unidades/1')->assertUnauthorized();
});

it('el listado va en orden jerárquico con nivel, unidad padre, contadores y total', function () {
    actuarComoAdmin();
    $a = arbolDeEjemplo();
    posicionesDeUnidad($a['talento'], 'Ocupada', 2);

    $respuesta = $this->getJson('/api/unidades')->assertOk()
        ->assertJsonPath('data.*.nombre', ['Gerencia general', 'Talento humano', 'Reclutamiento', 'Tecnología', 'Desarrollo'])
        ->assertJsonPath('data.*.nivel', [0, 1, 2, 1, 2])
        ->assertJsonPath('meta.total', 5)
        ->assertJsonStructure(['data' => [['id', 'codigo', 'nombre', 'tipo' => ['id', 'nombre'], 'unidad_padre', 'nivel', 'estado', 'dependientes_activas', 'posiciones_vigentes']]]);

    expect($respuesta->json('data.0.unidad_padre'))->toBeNull()
        ->and($respuesta->json('data.2.unidad_padre.codigo'))->toBe($a['talento']->codigo)
        ->and($respuesta->json('data.1.posiciones_vigentes'))->toBe(2)
        ->and($respuesta->json('data.0.dependientes_activas'))->toBe(2);
});

it('el Usuario recibe solo activas aunque envíe otro estado', function () {
    $a = arbolDeEjemplo();
    UnidadModel::factory()->conPadre($a['raiz'])->inactiva()->create(['nombre' => 'Retirada']);
    actuarComoUsuario();

    $this->getJson('/api/unidades')->assertOk()->assertJsonPath('meta.total', 5);
    $this->getJson('/api/unidades?estado=Todas')->assertJsonPath('meta.total', 5);
    $this->getJson('/api/unidades?estado=Inactiva')->assertJsonPath('meta.total', 5);
});

it('el Administrador ve por defecto las activas y puede pedir Inactiva o Todas', function () {
    $a = arbolDeEjemplo();
    UnidadModel::factory()->conPadre($a['raiz'])->inactiva()->create(['nombre' => 'Retirada']);
    actuarComoAdmin();

    $this->getJson('/api/unidades')->assertJsonPath('meta.total', 5);
    $this->getJson('/api/unidades?estado=Inactiva')->assertJsonPath('data.*.nombre', ['Retirada']);
    $this->getJson('/api/unidades?estado=Todas')->assertJsonPath('meta.total', 6);
    $this->getJson('/api/unidades?estado=Otra')->assertUnprocessable();
});

it('busca por código, nombre y tipo sin distinguir mayúsculas ni tildes', function () {
    actuarComoAdmin();
    $a = arbolDeEjemplo();
    $a['desarrollo']->update(['tipo_unidad_id' => tipoDeUnidad('Área')->id]);

    $this->getJson('/api/unidades?q=TECNOLOGIA')->assertJsonPath('data.*.nombre', ['Tecnología']);
    $this->getJson('/api/unidades?q=area')->assertJsonPath('data.*.nombre', ['Desarrollo']);
    $this->getJson('/api/unidades?q='.strtolower($a['talento']->codigo))->assertJsonPath('data.*.nombre', ['Talento humano']);
    $this->getJson('/api/unidades?q=zzzz')->assertJsonPath('meta.total', 0)->assertJsonPath('data', []);
});

it('trata % y _ como literales en la búsqueda', function () {
    actuarComoAdmin();
    $raiz = UnidadModel::factory()->raiz()->create(['nombre' => 'Raiz']);
    UnidadModel::factory()->conPadre($raiz)->create(['nombre' => 'Costos 100% reales']);

    $this->getJson('/api/unidades?q=100%25')->assertJsonPath('data.*.nombre', ['Costos 100% reales']);
    $this->getJson('/api/unidades?q=_')->assertJsonPath('meta.total', 0);
});

it('las filas encontradas conservan su nivel real y no se añaden sus unidades superiores', function () {
    actuarComoAdmin();
    arbolDeEjemplo();

    $respuesta = $this->getJson('/api/unidades?q=reclutamiento')->assertOk()->assertJsonPath('meta.total', 1);

    expect($respuesta->json('data.0.nivel'))->toBe(2)
        ->and($respuesta->json('data.0.unidad_padre.nombre'))->toBe('Talento humano');
});

it('no pagina: devuelve todas las unidades', function () {
    actuarComoAdmin();
    $raiz = UnidadModel::factory()->raiz()->create();
    UnidadModel::factory()->count(30)->conPadre($raiz)->create();

    $this->getJson('/api/unidades')->assertJsonPath('meta.total', 31)->assertJsonCount(31, 'data');
});

it('el detalle trae versión, fechas, tipo, padre y dependientes de todo estado', function () {
    actuarComoUsuario();
    $a = arbolDeEjemplo();
    UnidadModel::factory()->conPadre($a['tecnologia'])->inactiva()->create(['nombre' => 'Operaciones']);

    $respuesta = $this->getJson("/api/unidades/{$a['tecnologia']->id}")->assertOk()
        ->assertJsonPath('data.version', 1)
        ->assertJsonPath('data.fecha_creacion', now()->toDateString())
        ->assertJsonPath('data.fecha_supresion', null)
        ->assertJsonPath('data.unidad_padre.nombre', 'Gerencia general')
        ->assertJsonPath('data.dependientes_activas', 1);

    expect(array_column($respuesta->json('data.dependientes'), 'estado', 'nombre'))->toBe(['Desarrollo' => 'Activa', 'Operaciones' => 'Inactiva']);
});

it('el detalle de una unidad inactiva responde 200 a ambos roles y trae su fecha de supresión', function () {
    $a = arbolDeEjemplo();
    $inactiva = UnidadModel::factory()->conPadre($a['raiz'])->inactiva()->create();

    actuarComoUsuario();
    $this->getJson("/api/unidades/{$inactiva->id}")->assertOk()->assertJsonPath('data.estado', 'Inactiva')
        ->assertJsonPath('data.fecha_supresion', now()->toDateString());

    actuarComoAdmin();
    $this->getJson("/api/unidades/{$inactiva->id}")->assertOk();
});

it('una unidad que no existe responde 404', function () {
    actuarComoAdmin();

    $this->getJson('/api/unidades/999')->assertNotFound()->assertExactJson(['message' => 'La unidad no existe.']);
});

it('la auditoría con nombres de usuario solo la recibe el Administrador', function () {
    $ana = UserModel::factory()->admin()->create(['name' => 'Ana Administradora']);
    $luis = UserModel::factory()->admin()->create(['name' => 'Luis Editor']);
    $raiz = UnidadModel::factory()->raiz()->create(['created_by' => $ana->id]);
    $unidad = UnidadModel::factory()->conPadre($raiz)->create(['created_by' => $ana->id, 'updated_by' => $luis->id, 'version' => 3]);

    Sanctum::actingAs($ana, ['admin']);
    $admin = $this->getJson("/api/unidades/{$unidad->id}")->assertOk();
    expect($admin->json('data.auditoria.created_by'))->toBe(['id' => $ana->id, 'nombre' => 'Ana Administradora'])
        ->and($admin->json('data.auditoria.updated_by'))->toBe(['id' => $luis->id, 'nombre' => 'Luis Editor'])
        ->and($admin->json('data.auditoria.created_at'))->not->toBeNull();

    actuarComoUsuario();
    $this->getJson("/api/unidades/{$unidad->id}")->assertOk()->assertJsonMissingPath('data.auditoria');
});

it('antes de la primera edición la auditoría no trae quién modificó', function () {
    $admin = UserModel::factory()->admin()->create();
    $raiz = UnidadModel::factory()->raiz()->create(['created_by' => $admin->id]);
    Sanctum::actingAs($admin, ['admin']);

    $respuesta = $this->getJson("/api/unidades/{$raiz->id}")->assertOk();

    expect($respuesta->json('data.auditoria.updated_by'))->toBeNull()->and($respuesta->json('data.auditoria.updated_at'))->toBeNull();
});

it('el nombre de la unidad padre o del tipo cambiado se refleja de inmediato', function () {
    actuarComoAdmin();
    $a = arbolDeEjemplo();
    $a['talento']->update(['nombre' => 'Personas']);

    $this->getJson("/api/unidades/{$a['reclutamiento']->id}")->assertJsonPath('data.unidad_padre.nombre', 'Personas');
});
