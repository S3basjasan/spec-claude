<?php

use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;

function tokenDe(UserModel $usuario): string
{
    return test()->postJson('/api/auth/login', ['email' => $usuario->email, 'password' => 'password'])
        ->json('data.token');
}

it('cierra sesión con 204 y el token deja de ser válido', function () {
    $usuario = UserModel::factory()->create();
    $token = tokenDe($usuario);

    $this->withToken($token)->postJson('/api/auth/logout')->assertNoContent();

    $this->app['auth']->forgetGuards();
    $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
    expect($usuario->tokens()->count())->toBe(0);
});

it('revoca solo el token actual y conserva las demás sesiones', function () {
    $usuario = UserModel::factory()->create();
    $primero = tokenDe($usuario);
    $segundo = tokenDe($usuario);

    $this->withToken($primero)->postJson('/api/auth/logout')->assertNoContent();

    $this->app['auth']->forgetGuards();
    $this->withToken($segundo)->getJson('/api/auth/me')->assertOk();
    expect($usuario->tokens()->count())->toBe(1);
});

it('rechaza con 401 sin token', function () {
    $this->postJson('/api/auth/logout')->assertUnauthorized()->assertExactJson(['message' => 'No autenticado.']);
});
