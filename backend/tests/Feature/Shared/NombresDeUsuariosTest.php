<?php

use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Modules\Shared\Presentation\Facades\SharedFacade;

it('devuelve el nombre de los usuarios existentes y omite los ids que no existen', function () {
    $ana = UserModel::factory()->admin()->create(['name' => 'Ana']);
    $luis = UserModel::factory()->create(['name' => 'Luis']);

    expect(SharedFacade::getNombresDeUsuarios([$ana->id, $luis->id, 9999]))->toBe([$ana->id => 'Ana', $luis->id => 'Luis']);
});

it('con una lista vacía devuelve vacío', function () {
    expect(SharedFacade::getNombresDeUsuarios([]))->toBe([]);
});

it('solo devuelve id y nombre, nunca el correo ni el rol', function () {
    $ana = UserModel::factory()->admin()->create(['name' => 'Ana']);

    $resultado = SharedFacade::getNombresDeUsuarios([$ana->id]);

    expect($resultado)->toBe([$ana->id => 'Ana'])->and($resultado[$ana->id])->toBeString();
});

it('ignora los ids repetidos', function () {
    $ana = UserModel::factory()->create(['name' => 'Ana']);

    expect(SharedFacade::getNombresDeUsuarios([$ana->id, $ana->id]))->toBe([$ana->id => 'Ana']);
});
