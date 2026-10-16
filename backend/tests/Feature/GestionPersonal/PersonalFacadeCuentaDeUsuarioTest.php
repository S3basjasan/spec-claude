<?php

use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\ColaboradorCuentaModel;
use App\Modules\GestionPersonal\Presentation\Facades\PersonalFacade;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;

it('devuelve el tenant y la ficha asociada a la cuenta del usuario', function () {
    $ficha = fichaDe([], 1);
    $usuario = UserModel::factory()->create(['tenant_id' => 1]);
    ColaboradorCuentaModel::query()->create([
        'tenant_id' => 1, 'user_id' => $usuario->id, 'colaborador_id' => $ficha->id, 'puede_consultar_ficha' => true,
    ]);

    expect(PersonalFacade::getCuentaDeUsuario($usuario->id))
        ->toBe(['tenant_id' => 1, 'colaborador_id' => $ficha->id]);
});

it('devuelve el tenant correcto de la cuenta aunque sea otro tenant', function () {
    tenantDos();
    $ficha = fichaDe([], 2);
    $usuario = UserModel::factory()->create(['tenant_id' => 2]);
    ColaboradorCuentaModel::query()->create([
        'tenant_id' => 2, 'user_id' => $usuario->id, 'colaborador_id' => $ficha->id, 'puede_consultar_ficha' => false,
    ]);

    expect(PersonalFacade::getCuentaDeUsuario($usuario->id)['tenant_id'])->toBe(2);
});

it('devuelve null si el usuario no tiene ficha asociada', function () {
    $usuario = UserModel::factory()->create(['tenant_id' => 1]);

    expect(PersonalFacade::getCuentaDeUsuario($usuario->id))->toBeNull();
});
