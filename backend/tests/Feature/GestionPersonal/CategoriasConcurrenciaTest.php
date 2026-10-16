<?php

use App\Modules\GestionPersonal\Domain\Exceptions\CategoriaInactiva;
use App\Modules\GestionPersonal\Domain\Exceptions\CategoriaModificada;
use App\Modules\GestionPersonal\Domain\Exceptions\NombreDeCategoriaDuplicado;
use App\Modules\GestionPersonal\Domain\Repositories\CategoriaRepositoryInterface;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\CategoriaDocumentalModel;
use App\Modules\GestionPersonal\Presentation\Facades\PersonalFacade;
use Illuminate\Support\Facades\DB;

/*
 * Reproducción determinista de las carreras: el proyecto no tiene un arnés multiproceso. Lo que decide al ganador en
 * la base de datos es la restricción única y la condición `WHERE version = ?` del repositorio.
 */

it('dos creaciones con el mismo nombre: una gana y la otra recibe 422 de nombre', function () {
    actuarComoAdminDeTenant();

    $estados = [
        $this->postJson(URL_CATEGORIAS, ['nombre' => 'Contratos'])->status(),
        $this->postJson(URL_CATEGORIAS, ['nombre' => ' CONTRATOS '])->status(),
    ];

    expect($estados)->toBe([201, 422])->and(CategoriaDocumentalModel::query()->count())->toBe(1);
});

it('la restricción única decide aunque se salte la comprobación previa', function () {
    $repo = app(CategoriaRepositoryInterface::class);
    $repo->crear(1, 1, 'Contratos', 'contratos', 'contratos');

    expect(fn () => $repo->crear(1, 1, 'CONTRATOS', 'contratos', 'contratos'))->toThrow(NombreDeCategoriaDuplicado::class);
    $repo->crear(2, 1, 'Contratos', 'contratos', 'contratos');
    expect(CategoriaDocumentalModel::query()->count())->toBe(2);
});

it('dos ediciones con la misma versión: una gana y la otra recibe 409', function () {
    actuarComoAdminDeTenant();
    $categoria = CategoriaDocumentalModel::factory()->create(['nombre' => 'Contratos']);

    $estados = [
        $this->patchJson(urlCategoria($categoria->id), ['nombre' => 'A', 'version' => 1])->status(),
        $this->patchJson(urlCategoria($categoria->id), ['nombre' => 'B', 'version' => 1])->status(),
    ];

    expect($estados)->toBe([200, 409])->and($categoria->fresh()->nombre)->toBe('A')->and($categoria->fresh()->version)->toBe(2);
});

it('editar y desactivar con la misma versión: la segunda pierde', function () {
    actuarComoAdminDeTenant();
    $categoria = CategoriaDocumentalModel::factory()->create(['nombre' => 'Contratos']);

    $this->patchJson(urlCategoria($categoria->id, '/estado'), ['estado' => 'Inactiva', 'version' => 1])->assertOk();
    $this->patchJson(urlCategoria($categoria->id), ['nombre' => 'Otra', 'version' => 1])->assertStatus(409);

    expect($categoria->fresh()->nombre)->toBe('Contratos')->and($categoria->fresh()->estado)->toBe('Inactiva');
});

it('el repositorio decide por la versión aunque el caso de uso haya leído un estado anterior', function () {
    $categoria = CategoriaDocumentalModel::factory()->create();
    $repo = app(CategoriaRepositoryInterface::class);

    $repo->actualizar(1, $categoria->id, 1, 1, ['estado' => 'Inactiva']);

    expect(fn () => $repo->actualizar(1, $categoria->id, 1, 1, ['estado' => 'Activa']))->toThrow(CategoriaModificada::class)
        ->and($categoria->fresh()->estado)->toBe('Inactiva');
});

it('si la carga confirma primero, el documento queda asociado y luego se permite desactivar', function () {
    actuarComoAdminDeTenant();
    $categoria = CategoriaDocumentalModel::factory()->create();

    DB::transaction(fn () => PersonalFacade::confirmarCategoriaActiva(1, $categoria->id));
    $this->patchJson(urlCategoria($categoria->id, '/estado'), ['estado' => 'Inactiva', 'version' => 1])->assertOk();

    expect($categoria->fresh()->estado)->toBe('Inactiva');
});

it('si desactivar confirma primero, la carga se rechaza', function () {
    actuarComoAdminDeTenant();
    $categoria = CategoriaDocumentalModel::factory()->create();

    $this->patchJson(urlCategoria($categoria->id, '/estado'), ['estado' => 'Inactiva', 'version' => 1])->assertOk();

    expect(fn () => DB::transaction(fn () => PersonalFacade::confirmarCategoriaActiva(1, $categoria->id)))->toThrow(CategoriaInactiva::class);
});
