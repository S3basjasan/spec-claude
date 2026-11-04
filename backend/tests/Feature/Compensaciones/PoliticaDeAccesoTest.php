<?php

use App\Modules\Compensaciones\Application\Services\PoliticaDeAcceso;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Laravel\Sanctum\Sanctum;

it('concede categorias.gestionar a un token con la capacidad admin', function () {
    $usuario = UserModel::factory()->admin()->create();
    Sanctum::actingAs($usuario, ['admin']);

    expect((new PoliticaDeAcceso)->puede(auth()->user(), PoliticaDeAcceso::CATEGORIAS_GESTIONAR))->toBeTrue();
});

it('niega categorias.gestionar a un token sin la capacidad admin', function () {
    Sanctum::actingAs(UserModel::factory()->create(), ['user']);

    expect((new PoliticaDeAcceso)->puede(auth()->user(), PoliticaDeAcceso::CATEGORIAS_GESTIONAR))->toBeFalse();
});

it('nombra el permiso con el formato recurso.acción', function () {
    expect(PoliticaDeAcceso::CATEGORIAS_GESTIONAR)->toBe('categorias.gestionar')
        ->toMatch('/^[a-z]+\.[a-z]+$/');
});

it('concede parametros.gestionar solo con la capacidad admin', function () {
    Sanctum::actingAs(UserModel::factory()->admin()->create(), ['admin']);
    expect((new PoliticaDeAcceso)->puede(auth()->user(), PoliticaDeAcceso::PARAMETROS_GESTIONAR))->toBeTrue()
        ->and(PoliticaDeAcceso::PARAMETROS_GESTIONAR)->toBe('parametros.gestionar');

    Sanctum::actingAs(UserModel::factory()->create(), ['user']);
    expect((new PoliticaDeAcceso)->puede(auth()->user(), PoliticaDeAcceso::PARAMETROS_GESTIONAR))->toBeFalse();
});

it('deniega por defecto un permiso desconocido, incluso con admin', function () {
    Sanctum::actingAs(UserModel::factory()->admin()->create(), ['admin']);

    expect((new PoliticaDeAcceso)->puede(auth()->user(), 'otro.permiso'))->toBeFalse();
});
