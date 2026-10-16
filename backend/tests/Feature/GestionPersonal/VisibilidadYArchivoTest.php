<?php

use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\DocumentoColaboradorModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => almacenDeDocumentos());

function documentoDe(int $fichaId, array $atributos = []): DocumentoColaboradorModel
{
    return documentoConArchivo(array_merge(['colaborador_id' => $fichaId, 'categoria_id' => categoriaActiva()->id], $atributos));
}

it('cambia la visibilidad de un disponible con la versión vigente y registra la auditoría', function () {
    $admin = actuarComoAdminDeTenant();
    $ficha = fichaDe();
    $documento = documentoDe($ficha->id);

    $respuesta = $this->patchJson(urlDocumentos($ficha->id, "/{$documento->id}/visibilidad"), ['visible_colaborador' => true, 'version' => 1])->assertOk();

    expect($respuesta->json('data.visible_colaborador'))->toBeTrue()
        ->and($respuesta->json('data.version'))->toBe(2)
        ->and($documento->fresh()->updated_by)->toBe($admin->id);
});

it('el mismo valor con la versión vigente no escribe nada', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();
    $documento = documentoDe($ficha->id);
    DB::table('documentos_colaborador')->where('id', $documento->id)->update(['updated_at' => '2024-01-01 10:00:00']);

    $this->patchJson(urlDocumentos($ficha->id, "/{$documento->id}/visibilidad"), ['visible_colaborador' => false, 'version' => 1])->assertOk()->assertJsonPath('data.version', 1);

    expect($documento->fresh()->version)->toBe(1)->and($documento->fresh()->updated_at->format('Y-m-d H:i:s'))->toBe('2024-01-01 10:00:00');
});

it('una versión obsoleta devuelve 409 y conserva la primera escritura', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();
    $documento = documentoDe($ficha->id);
    $url = urlDocumentos($ficha->id, "/{$documento->id}/visibilidad");

    $this->patchJson($url, ['visible_colaborador' => true, 'version' => 1])->assertOk();
    $this->patchJson($url, ['visible_colaborador' => false, 'version' => 1])->assertStatus(409)->assertJsonPath('code', 'DOCUMENTO_MODIFICADO');

    expect($documento->fresh()->visible_colaborador)->toBeTrue();
});

it('exige la versión y un booleano', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();
    $documento = documentoDe($ficha->id);
    $url = urlDocumentos($ficha->id, "/{$documento->id}/visibilidad");

    $this->patchJson($url, ['visible_colaborador' => true])->assertUnprocessable()->assertJsonValidationErrors(['version']);
    $this->patchJson($url, ['version' => 1])->assertUnprocessable()->assertJsonValidationErrors(['visible_colaborador']);
    $this->patchJson($url, ['visible_colaborador' => 'quizá', 'version' => 1])->assertUnprocessable();
});

it('archiva conservando archivo y metadatos y registra quién y cuándo (EXP-DOC-CA04)', function () {
    $admin = actuarComoAdminDeTenant();
    $ficha = fichaDe();
    $documento = documentoDe($ficha->id, ['visible_colaborador' => true]);

    $respuesta = $this->patchJson(urlDocumentos($ficha->id, "/{$documento->id}/archivo"), ['version' => 1])->assertOk();

    $fresco = $documento->fresh();
    expect($respuesta->json('data.estado'))->toBe('Archivado')
        ->and($respuesta->json('data.archivado_por'))->toBe($admin->id)
        ->and($respuesta->json('data.archivado_en'))->not->toBeNull()
        ->and($fresco->visible_colaborador)->toBeTrue()
        ->and(Storage::disk(discoDeDocumentos())->exists($fresco->object_key))->toBeTrue()
        ->and($fresco->titulo)->toBe($documento->titulo)->and($fresco->version)->toBe(2);

    $habitual = array_column($this->getJson(urlDocumentos($ficha->id))->json('data'), 'id');
    $archivados = array_column($this->getJson(urlDocumentos($ficha->id).'?estado=Archivado')->json('data'), 'id');
    expect($habitual)->not->toContain($documento->id)->and($archivados)->toContain($documento->id);
    $this->get(urlDocumentos($ficha->id, "/{$documento->id}/contenido"))->assertOk();
});

it('un documento archivado no admite cambiar visibilidad ni archivarse de nuevo', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();
    $documento = documentoDe($ficha->id);
    $this->patchJson(urlDocumentos($ficha->id, "/{$documento->id}/archivo"), ['version' => 1])->assertOk();

    $this->patchJson(urlDocumentos($ficha->id, "/{$documento->id}/visibilidad"), ['visible_colaborador' => true, 'version' => 2])->assertStatus(409);
    $this->patchJson(urlDocumentos($ficha->id, "/{$documento->id}/archivo"), ['version' => 2])->assertStatus(409);
    expect($documento->fresh()->version)->toBe(2)->and($documento->fresh()->visible_colaborador)->toBeFalse();
});

it('no existe restauración, edición ni eliminación', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();
    $documento = documentoDe($ficha->id);
    $base = urlDocumentos($ficha->id, "/{$documento->id}");

    expect($this->deleteJson($base)->status())->toBeIn([404, 405])
        ->and($this->patchJson($base, ['titulo' => 'Otro', 'version' => 1])->status())->toBeIn([404, 405])
        ->and($this->putJson($base, ['titulo' => 'Otro'])->status())->toBeIn([404, 405])
        ->and($this->patchJson("{$base}/restaurar", ['version' => 1])->status())->toBeIn([404, 405])
        ->and($documento->fresh()->titulo)->toBe($documento->titulo);
});

it('desactivar la categoría no cambia estado, visibilidad ni acceso, y no impide las operaciones', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();
    $categoria = categoriaActiva();
    $documento = documentoDe($ficha->id, ['categoria_id' => $categoria->id, 'visible_colaborador' => true]);
    $this->patchJson("/api/categorias-documentales/{$categoria->id}/estado", ['estado' => 'Inactiva', 'version' => 1])->assertOk();

    expect($documento->fresh()->estado)->toBe('Disponible')->and($documento->fresh()->visible_colaborador)->toBeTrue();
    $this->get(urlDocumentos($ficha->id, "/{$documento->id}/contenido"))->assertOk();
    $this->patchJson(urlDocumentos($ficha->id, "/{$documento->id}/visibilidad"), ['visible_colaborador' => false, 'version' => 1])->assertOk();
    $this->patchJson(urlDocumentos($ficha->id, "/{$documento->id}/archivo"), ['version' => 2])->assertOk();
});

it('responde 404 de otro tenant o de otra ficha y deniega al colaborador', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();
    $otra = fichaDe();
    $ajena = fichaDe([], 2);
    $deOtra = documentoDe($otra->id);
    $deAjena = documentoConArchivo(['tenant_id' => 2, 'colaborador_id' => $ajena->id, 'categoria_id' => categoriaActiva([], 2)->id]);

    $this->patchJson(urlDocumentos($ficha->id, "/{$deOtra->id}/archivo"), ['version' => 1])->assertNotFound();
    $this->patchJson(urlDocumentos($ajena->id, "/{$deAjena->id}/visibilidad"), ['visible_colaborador' => true, 'version' => 1])->assertNotFound();
    expect($deOtra->fresh()->estado)->toBe('Disponible')->and($deAjena->fresh()->visible_colaborador)->toBeFalse();

    actuarComoUsuarioDeTenant();
    $this->patchJson(urlDocumentos($otra->id, "/{$deOtra->id}/archivo"), ['version' => 1])->assertForbidden();
    $this->patchJson(urlDocumentos($otra->id, "/{$deOtra->id}/visibilidad"), ['visible_colaborador' => true, 'version' => 1])->assertForbidden();
    expect($deOtra->fresh()->estado)->toBe('Disponible');
});

it('respeta el interruptor de documentos', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe();
    $documento = documentoDe($ficha->id);
    config(['hrms.gestion_personal.documentos.habilitado' => false]);

    $this->patchJson(urlDocumentos($ficha->id, "/{$documento->id}/archivo"), ['version' => 1])->assertStatus(503);
    $this->patchJson(urlDocumentos($ficha->id, "/{$documento->id}/visibilidad"), ['visible_colaborador' => true, 'version' => 1])->assertStatus(503);
    $this->get(urlDocumentos($ficha->id, "/{$documento->id}/contenido"))->assertOk();
});
