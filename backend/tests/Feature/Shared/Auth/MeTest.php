<?php

use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;

function iniciarSesionComo(UserModel $usuario): string
{
    return test()->postJson('/api/auth/login', ['email' => $usuario->email, 'password' => 'password'])
        ->json('data.token');
}

it('devuelve identidad y rol del administrador', function () {
    $admin = UserModel::factory()->admin()->create();

    $this->withToken(iniciarSesionComo($admin))
        ->getJson('/api/auth/me')
        ->assertOk()
        ->assertExactJson(['data' => ['id' => $admin->id, 'name' => $admin->name, 'email' => $admin->email, 'role' => 'admin', 'tenant' => ['id' => 1, 'nombre' => 'Altavista S.A.']]]);
});

it('devuelve identidad y rol del usuario', function () {
    $usuario = UserModel::factory()->create();

    $this->withToken(iniciarSesionComo($usuario))
        ->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('data.role', 'user')
        ->assertJsonMissingPath('data.password');
});

it('rechaza con 401 sin token', function () {
    $this->getJson('/api/auth/me')->assertUnauthorized()->assertExactJson(['message' => 'No autenticado.']);
});

it('rechaza con 401 con un token inválido', function () {
    $this->withToken('999|token-inventado')->getJson('/api/auth/me')->assertUnauthorized();
});
