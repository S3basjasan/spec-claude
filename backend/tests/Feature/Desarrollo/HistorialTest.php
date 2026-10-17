<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\AreaFormativaModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\CursoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\HistorialFormativoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicrocontenidoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Laravel\Sanctum\Sanctum;

it('registra asociaciones, desasociaciones, cambio de área y renombrado, del más reciente al más antiguo', function () {
    $competencia = CompetenciaModel::factory()->create();
    $curso = CursoModel::factory()->create(['nombre' => 'Curso uno']);
    $micro = MicrocontenidoModel::factory()->publicado()->create(['titulo' => 'Micro uno']);
    $area = AreaFormativaModel::factory()->create(['nombre' => 'Área vieja']);
    $admin = UserModel::factory()->admin()->create(['name' => 'Marta Ríos']);
    Sanctum::actingAs($admin, ['admin']);

    $this->putJson("/api/catalogo-formativo/{$competencia->id}", cuerpoFormativo([
        'area_formativa_id' => $area->id, 'curso_ids' => [$curso->id], 'microcontenido_ids' => [$micro->id],
    ]))->assertOk();
    $this->putJson("/api/catalogo-formativo/{$competencia->id}", cuerpoFormativo(['area_formativa_id' => $area->id, 'version' => 1]))->assertOk();
    $this->patchJson("/api/catalogo-formativo/areas/{$area->id}", ['nombre' => 'Área nueva'])->assertOk();

    $datos = $this->getJson("/api/catalogo-formativo/{$competencia->id}/historial")->assertOk()->json('data');

    $resumen = array_map(fn ($i) => [$i['tipo_cambio'], $i['accion']], $datos);
    expect($resumen)->toBe([
        ['AreaFormativa', 'Renombrado'],
        ['Microcontenido', 'Quitado'],
        ['Curso', 'Quitado'],
        ['Microcontenido', 'Asociado'],
        ['Curso', 'Asociado'],
        ['AreaFormativa', 'Cambiado'],
    ])
        ->and($datos[0]['usuario'])->toBe('Marta Ríos')
        ->and($datos[0]['valor_anterior'])->toBe('Área vieja')
        ->and($datos[0]['valor_nuevo'])->toBe('Área nueva')
        ->and($datos[2]['recurso'])->toBe('Curso uno')
        ->and($datos[2]['fecha'])->not->toBe('');
});

it('un guardado sin cambios no agrega entradas', function () {
    $competencia = CompetenciaModel::factory()->create();
    actuarComoAdmin();

    $this->putJson("/api/catalogo-formativo/{$competencia->id}", cuerpoFormativo())->assertOk();

    expect(HistorialFormativoModel::query()->count())->toBe(0);
});

it('el detalle muestra la última configuración con el usuario y la fecha', function () {
    $competencia = CompetenciaModel::factory()->create();
    $curso = CursoModel::factory()->create();
    Sanctum::actingAs(UserModel::factory()->admin()->create(['name' => 'Luis Mora']), ['admin']);

    expect($this->getJson("/api/catalogo-formativo/{$competencia->id}")->json('data.ultima_configuracion'))->toBeNull();

    $this->putJson("/api/catalogo-formativo/{$competencia->id}", cuerpoFormativo(['curso_ids' => [$curso->id]]))->assertOk();

    $ultima = $this->getJson("/api/catalogo-formativo/{$competencia->id}")->json('data.ultima_configuracion');
    expect($ultima['usuario'])->toBe('Luis Mora')->and($ultima['fecha'])->not->toBeEmpty();
});

it('pagina el historial', function () {
    $competencia = CompetenciaModel::factory()->create();
    $cursos = CursoModel::factory()->count(3)->create();
    actuarComoAdmin();
    $this->putJson("/api/catalogo-formativo/{$competencia->id}", cuerpoFormativo(['curso_ids' => $cursos->pluck('id')->all()]))->assertOk();

    $respuesta = $this->getJson("/api/catalogo-formativo/{$competencia->id}/historial?per_page=2&page=2");

    expect($respuesta->json('data'))->toHaveCount(1)
        ->and($respuesta->json('meta.total'))->toBe(3)
        ->and($respuesta->json('meta.last_page'))->toBe(2);
});

it('responde 404, 403 y 401 según corresponda y no existen rutas de edición ni borrado', function () {
    $competencia = CompetenciaModel::factory()->create();
    $this->getJson("/api/catalogo-formativo/{$competencia->id}/historial")->assertUnauthorized();

    actuarComoUsuario();
    $this->getJson("/api/catalogo-formativo/{$competencia->id}/historial")->assertForbidden();

    actuarComoAdmin();
    $this->getJson('/api/catalogo-formativo/999999/historial')->assertNotFound();
    $this->deleteJson("/api/catalogo-formativo/{$competencia->id}/historial")->assertStatus(405);
    $this->putJson("/api/catalogo-formativo/{$competencia->id}/historial", [])->assertStatus(405);
});
