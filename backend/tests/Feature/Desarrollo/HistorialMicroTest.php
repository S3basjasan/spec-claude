<?php

use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Laravel\Sanctum\Sanctum;

it('lista los cambios de estado, el más reciente primero, con usuario y fecha', function () {
    $admin = UserModel::factory()->admin()->create(['name' => 'Marta Ríos']);
    Sanctum::actingAs($admin, ['admin']);

    $id = $this->postJson('/api/microcontenidos', cuerpoMicro())->json('data.id');
    $this->patchJson("/api/microcontenidos/$id/estado", ['estado' => 'Publicado'])->assertOk();
    $this->patchJson("/api/microcontenidos/$id/estado", ['estado' => 'Archivado'])->assertOk();

    $respuesta = $this->getJson("/api/microcontenidos/$id/historial")->assertOk();
    $items = $respuesta->json('data');

    expect($items)->toHaveCount(3)
        ->and([$items[0]['estado_anterior'], $items[0]['estado_nuevo']])->toBe(['Publicado', 'Archivado'])
        ->and([$items[1]['estado_anterior'], $items[1]['estado_nuevo']])->toBe(['Borrador', 'Publicado'])
        ->and([$items[2]['estado_anterior'], $items[2]['estado_nuevo']])->toBe([null, 'Borrador'])
        ->and($items[0]['usuario'])->toBe('Marta Ríos')
        ->and($items[0]['fecha'])->not->toBeNull()
        ->and($respuesta->json('meta.total'))->toBe(3);
});

it('pagina el historial', function () {
    actuarComoAdmin();
    $id = $this->postJson('/api/microcontenidos', cuerpoMicro())->json('data.id');
    $this->patchJson("/api/microcontenidos/$id/estado", ['estado' => 'Publicado']);
    $this->patchJson("/api/microcontenidos/$id/estado", ['estado' => 'Archivado']);

    $pagina = $this->getJson("/api/microcontenidos/$id/historial?per_page=2&page=2")->assertOk();

    expect($pagina->json('data'))->toHaveCount(1)->and($pagina->json('meta.last_page'))->toBe(2);
});

it('el detalle del administrador trae la auditoría de creación y modificación con nombres', function () {
    $admin = UserModel::factory()->admin()->create(['name' => 'Ana Admin']);
    Sanctum::actingAs($admin, ['admin']);
    $id = $this->postJson('/api/microcontenidos', cuerpoMicro())->json('data.id');

    $otro = UserModel::factory()->admin()->create(['name' => 'Luis Editor']);
    Sanctum::actingAs($otro, ['admin']);
    $this->putJson("/api/microcontenidos/$id", cuerpoMicro(['titulo' => 'Editado']))->assertOk();

    $datos = $this->getJson("/api/microcontenidos/$id")->json('data');

    expect($datos['created_by'])->toBe('Ana Admin')->and($datos['updated_by'])->toBe('Luis Editor')
        ->and($datos['created_at'])->not->toBeNull()->and($datos['updated_at'])->not->toBeNull();
});

it('el historial es de solo inserción: no hay rutas para editarlo ni borrarlo', function () {
    actuarComoAdmin();
    $id = $this->postJson('/api/microcontenidos', cuerpoMicro())->json('data.id');

    $this->putJson("/api/microcontenidos/$id/historial", [])->assertStatus(405);
    $this->deleteJson("/api/microcontenidos/$id/historial")->assertStatus(405);
    $this->patchJson("/api/microcontenidos/$id/historial", [])->assertStatus(405);
});

it('responde 404 si no existe, 403 al colaborador y 401 sin sesión', function () {
    $micro = microDe();
    $this->getJson("/api/microcontenidos/{$micro->id}/historial")->assertUnauthorized();

    actuarComoUsuario();
    $this->getJson("/api/microcontenidos/{$micro->id}/historial")->assertForbidden();

    actuarComoAdmin();
    $this->getJson('/api/microcontenidos/9999/historial')->assertNotFound();
});
