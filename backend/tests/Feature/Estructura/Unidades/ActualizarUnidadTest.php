<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\AuditoriaCambioModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\UnidadModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Laravel\Sanctum\Sanctum;

it('actualiza con cambios: sube la versión, fija quién editó y conserva código, estado y fechas', function () {
    $editor = UserModel::factory()->admin()->create(['name' => 'Luis Editor']);
    Sanctum::actingAs($editor, ['admin']);
    $a = arbolDeEjemplo();
    $a['talento']->update(['created_by' => 99]);

    $this->putJson("/api/unidades/{$a['talento']->id}", cuerpoDeUnidad($a['talento'], ['nombre' => 'Personas', 'tipo_unidad_id' => tipoDeUnidad('Gerencia')->id]))
        ->assertOk()
        ->assertJsonPath('data.nombre', 'Personas')
        ->assertJsonPath('data.tipo.nombre', 'Gerencia')
        ->assertJsonPath('data.version', 2)
        ->assertJsonPath('data.codigo', $a['talento']->codigo)
        ->assertJsonPath('data.estado', 'Activa')
        ->assertJsonPath('data.auditoria.updated_by.nombre', 'Luis Editor');

    $unidad = $a['talento']->fresh();
    expect($unidad->updated_by)->toBe($editor->id)->and($unidad->created_by)->toBe(99)->and($unidad->fecha_creacion)->toBe(now()->toDateString());
});

it('permite editar una unidad inactiva y sigue inactiva', function () {
    actuarComoAdmin();
    $a = arbolDeEjemplo();
    $inactiva = UnidadModel::factory()->conPadre($a['raiz'])->inactiva()->create(['nombre' => 'Operaciones']);

    $this->putJson("/api/unidades/{$inactiva->id}", cuerpoDeUnidad($inactiva, ['nombre' => 'Operaciones y logística']))
        ->assertOk()->assertJsonPath('data.estado', 'Inactiva')->assertJsonPath('data.fecha_supresion', now()->toDateString());
});

it('guardar sin cambios reales responde 200 sin tocar versión, updated_at, updated_by ni historial', function () {
    actuarComoAdmin();
    $a = arbolDeEjemplo();
    $antes = $a['talento']->fresh();
    $this->travel(5)->minutes();

    $this->putJson("/api/unidades/{$a['talento']->id}", cuerpoDeUnidad($a['talento'], ['nombre' => '  Talento humano  ']))
        ->assertOk()->assertJsonPath('data.version', 1);

    $despues = $a['talento']->fresh();
    expect($despues->updated_by)->toBeNull()
        ->and($despues->version)->toBe(1)
        ->and($despues->updated_at->equalTo($antes->updated_at))->toBeTrue()
        ->and(AuditoriaCambioModel::query()->count())->toBe(0);
});

it('devuelve 409 con una versión desactualizada y no modifica nada', function () {
    actuarComoAdmin();
    $a = arbolDeEjemplo();
    $this->putJson("/api/unidades/{$a['talento']->id}", cuerpoDeUnidad($a['talento'], ['nombre' => 'Primera']))->assertOk();

    $this->putJson("/api/unidades/{$a['talento']->id}", ['nombre' => 'Segunda', 'tipo_unidad_id' => $a['talento']->tipo_unidad_id, 'unidad_padre_id' => $a['raiz']->id, 'version' => 1])
        ->assertStatus(409)
        ->assertExactJson(['message' => 'La unidad cambió desde que la abriste. Recarga para ver la versión actual.']);

    expect($a['talento']->fresh()->nombre)->toBe('Primera')->and($a['talento']->fresh()->version)->toBe(2);
});

it('exige la versión', function () {
    actuarComoAdmin();
    $a = arbolDeEjemplo();
    $cuerpo = cuerpoDeUnidad($a['talento'], ['nombre' => 'Otra']);
    unset($cuerpo['version']);

    $this->putJson("/api/unidades/{$a['talento']->id}", $cuerpo)->assertUnprocessable()->assertJsonValidationErrors(['version']);
});

it('rechaza un código distinto, acepta el mismo y rechaza estado y fechas', function () {
    actuarComoAdmin();
    $a = arbolDeEjemplo();

    $this->putJson("/api/unidades/{$a['talento']->id}", cuerpoDeUnidad($a['talento'], ['nombre' => 'Otra', 'codigo' => 'UO-999']))
        ->assertUnprocessable()->assertJsonValidationErrors(['codigo']);
    $this->putJson("/api/unidades/{$a['talento']->id}", cuerpoDeUnidad($a['talento'], ['nombre' => 'Otra', 'codigo' => $a['talento']->codigo]))->assertOk();
    $this->putJson("/api/unidades/{$a['talento']->id}", cuerpoDeUnidad($a['talento'], ['estado' => 'Inactiva']))->assertUnprocessable()->assertJsonValidationErrors(['estado']);
    $this->putJson("/api/unidades/{$a['talento']->id}", cuerpoDeUnidad($a['talento'], ['fecha_supresion' => '2020-01-01']))->assertUnprocessable()->assertJsonValidationErrors(['fecha_supresion']);
    expect($a['talento']->fresh()->codigo)->toBe($a['talento']->codigo);
});

it('devuelve 404, 401 y 403 según corresponda', function () {
    $a = arbolDeEjemplo();
    $this->putJson("/api/unidades/{$a['talento']->id}", cuerpoDeUnidad($a['talento']))->assertUnauthorized();

    actuarComoUsuario();
    $this->putJson("/api/unidades/{$a['talento']->id}", cuerpoDeUnidad($a['talento']))
        ->assertForbidden()->assertExactJson(['message' => 'No tienes permisos para realizar esta acción.']);

    actuarComoAdmin();
    $this->putJson('/api/unidades/999', cuerpoDeUnidad($a['talento']))->assertNotFound()->assertExactJson(['message' => 'La unidad no existe.']);
});

it('aplica las mismas validaciones de nombre, tipo y padre que al crear', function () {
    actuarComoAdmin();
    $a = arbolDeEjemplo();

    $this->putJson("/api/unidades/{$a['talento']->id}", cuerpoDeUnidad($a['talento'], ['nombre' => '']))->assertUnprocessable()->assertJsonValidationErrors(['nombre']);
    $this->putJson("/api/unidades/{$a['talento']->id}", cuerpoDeUnidad($a['talento'], ['nombre' => str_repeat('x', 151)]))->assertUnprocessable()->assertJsonValidationErrors(['nombre']);
    $this->putJson("/api/unidades/{$a['talento']->id}", cuerpoDeUnidad($a['talento'], ['tipo_unidad_id' => 999]))->assertUnprocessable()->assertJsonValidationErrors(['tipo_unidad_id']);
    $this->putJson("/api/unidades/{$a['talento']->id}", cuerpoDeUnidad($a['talento'], ['unidad_padre_id' => 999]))->assertUnprocessable()->assertJsonValidationErrors(['unidad_padre_id']);
    expect($a['talento']->fresh()->version)->toBe(1);
});

it('conservar sin cambios el padre inactivo de una unidad inactiva se acepta; elegir otro inactivo no', function () {
    actuarComoAdmin();
    $a = arbolDeEjemplo();
    $padreInactivo = UnidadModel::factory()->conPadre($a['raiz'])->inactiva()->create(['nombre' => 'Padre retirado']);
    $otroInactivo = UnidadModel::factory()->conPadre($a['raiz'])->inactiva()->create(['nombre' => 'Otro retirado']);
    $hija = UnidadModel::factory()->conPadre($padreInactivo)->inactiva()->create(['nombre' => 'Hija retirada']);

    $this->putJson("/api/unidades/{$hija->id}", cuerpoDeUnidad($hija, ['nombre' => 'Hija renombrada']))->assertOk();
    $this->putJson("/api/unidades/{$hija->id}", cuerpoDeUnidad($hija, ['unidad_padre_id' => $otroInactivo->id]))
        ->assertUnprocessable()->assertJsonValidationErrors(['unidad_padre_id']);
    $this->putJson("/api/unidades/{$hija->id}", cuerpoDeUnidad($hija, ['unidad_padre_id' => $a['talento']->id]))->assertOk();
});
