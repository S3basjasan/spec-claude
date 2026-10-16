<?php

use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\CategoriaDocumentalModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

it('desactiva y reactiva con la versión vigente y registra la auditoría', function () {
    $admin = actuarComoAdminDeTenant();
    $categoria = CategoriaDocumentalModel::factory()->create();

    $this->patchJson(urlCategoria($categoria->id, '/estado'), ['estado' => 'Inactiva', 'version' => 1])
        ->assertOk()->assertJsonPath('data.estado', 'Inactiva')->assertJsonPath('data.version', 2);
    $this->patchJson(urlCategoria($categoria->id, '/estado'), ['estado' => 'Activa', 'version' => 2])
        ->assertOk()->assertJsonPath('data.estado', 'Activa')->assertJsonPath('data.version', 3);

    expect($categoria->fresh()->updated_by)->toBe($admin->id);
});

it('un estado igual al actual con versión vigente no escribe nada', function () {
    actuarComoAdminDeTenant();
    $categoria = CategoriaDocumentalModel::factory()->create(['updated_at' => '2024-01-01 10:00:00']);

    $this->patchJson(urlCategoria($categoria->id, '/estado'), ['estado' => 'Activa', 'version' => 1])->assertOk()->assertJsonPath('data.version', 1);

    expect($categoria->fresh()->version)->toBe(1)
        ->and($categoria->fresh()->updated_at->format('Y-m-d H:i:s'))->toBe('2024-01-01 10:00:00');
});

it('una versión obsoleta se rechaza aunque el resultado sería equivalente', function () {
    actuarComoAdminDeTenant();
    $categoria = CategoriaDocumentalModel::factory()->create();

    $this->patchJson(urlCategoria($categoria->id, '/estado'), ['estado' => 'Inactiva', 'version' => 1])->assertOk();
    $this->patchJson(urlCategoria($categoria->id, '/estado'), ['estado' => 'Inactiva', 'version' => 1])->assertStatus(409)
        ->assertJsonPath('code', 'CATEGORIA_MODIFICADA');

    expect($categoria->fresh()->version)->toBe(2);
});

it('rechaza un estado inválido o ausente y una versión ausente', function () {
    actuarComoAdminDeTenant();
    $categoria = CategoriaDocumentalModel::factory()->create();
    $url = urlCategoria($categoria->id, '/estado');

    $this->patchJson($url, ['estado' => 'Archivada', 'version' => 1])->assertUnprocessable()->assertJsonValidationErrors(['estado']);
    $this->patchJson($url, ['version' => 1])->assertUnprocessable()->assertJsonValidationErrors(['estado']);
    $this->patchJson($url, ['estado' => 'Inactiva'])->assertUnprocessable()->assertJsonValidationErrors(['version']);
    expect($categoria->fresh()->estado)->toBe('Activa');
});

it('responde 404 de otro tenant y deniega al colaborador', function () {
    actuarComoAdminDeTenant();
    $ajena = CategoriaDocumentalModel::factory()->create(['tenant_id' => 2]);
    $propia = CategoriaDocumentalModel::factory()->create();

    $this->patchJson(urlCategoria($ajena->id, '/estado'), ['estado' => 'Inactiva', 'version' => 1])->assertNotFound();
    expect($ajena->fresh()->estado)->toBe('Activa');

    actuarComoUsuarioDeTenant();
    $this->patchJson(urlCategoria($propia->id, '/estado'), ['estado' => 'Inactiva', 'version' => 1])->assertForbidden();
    expect($propia->fresh()->estado)->toBe('Activa');
});

it('respeta la escritura deshabilitada', function () {
    actuarComoAdminDeTenant();
    $categoria = CategoriaDocumentalModel::factory()->create();
    config(['hrms.gestion_personal.escritura_habilitada' => false]);

    $this->patchJson(urlCategoria($categoria->id, '/estado'), ['estado' => 'Inactiva', 'version' => 1])->assertStatus(503);
});

it('desactivar no consulta ni modifica documentos ni escribe en otras tablas', function () {
    actuarComoAdminDeTenant();
    $categoria = CategoriaDocumentalModel::factory()->create();
    $ficha = fichaDe();
    Storage::fake('local');
    $documento = documentoConArchivo(['colaborador_id' => $ficha->id, 'categoria_id' => $categoria->id, 'visible_colaborador' => true]);
    $antes = DB::table('documentos_colaborador')->where('id', $documento->id)->first();
    $consultas = [];
    DB::listen(function ($consulta) use (&$consultas) {
        $consultas[] = strtolower($consulta->sql);
    });

    $this->patchJson(urlCategoria($categoria->id, '/estado'), ['estado' => 'Inactiva', 'version' => 1])->assertOk();
    $durantePatch = $consultas;

    $despues = DB::table('documentos_colaborador')->where('id', $documento->id)->first();
    expect(CategoriaDocumentalModel::query()->count())->toBe(1)
        ->and((array) $despues)->toBe((array) $antes)
        ->and(array_filter($durantePatch, fn ($sql) => str_contains($sql, 'documentos_colaborador')))->toBe([]);
});

it('el estado fuera del conjunto permitido lo rechaza también la base de datos', function () {
    $categoria = CategoriaDocumentalModel::factory()->create();

    expect(fn () => CategoriaDocumentalModel::query()->whereKey($categoria->id)->update(['estado' => 'Archivada']))->toThrow(Exception::class);
});
