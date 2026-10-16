<?php

use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Modules\Shared\Presentation\Facades\SharedFacade;

it('getNombresDeUsuarios devuelve solo id => nombre de los usuarios existentes', function () {
    $ana = UserModel::factory()->create(['name' => 'Ana Salas']);
    $luis = UserModel::factory()->create(['name' => 'Luis Mora']);

    $nombres = SharedFacade::getNombresDeUsuarios([$ana->id, $luis->id, 999999]);

    expect($nombres)->toBe([$ana->id => 'Ana Salas', $luis->id => 'Luis Mora']);
});

it('getNombresDeUsuarios no expone correo ni otros datos y tolera una lista vacía', function () {
    $ana = UserModel::factory()->create(['name' => 'Ana Salas', 'email' => 'ana@hrms.test']);

    expect(json_encode(SharedFacade::getNombresDeUsuarios([$ana->id])))->not->toContain('ana@hrms.test')
        ->and(SharedFacade::getNombresDeUsuarios([]))->toBe([]);
});

it('getResumen sigue funcionando tras ampliar la Facade', function () {
    expect(SharedFacade::getResumen())->toBe(['modulo' => 'Shared']);
});
