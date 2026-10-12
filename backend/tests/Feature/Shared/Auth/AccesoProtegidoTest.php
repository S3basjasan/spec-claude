<?php

use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['api', 'auth:sanctum'])->get('/api/_test/protegida', fn () => response()->json(['ok' => true]));
    Route::middleware(['api', 'auth:sanctum', 'admin'])->get('/api/_test/admin', fn () => response()->json(['ok' => true]));
});

function tokenParaRol(UserModel $usuario): string
{
    return test()->postJson('/api/auth/login', ['email' => $usuario->email, 'password' => 'password'])
        ->json('data.token');
}

it('rechaza con 401 una ruta protegida sin sesión', function () {
    $this->getJson('/api/_test/protegida')->assertUnauthorized()->assertExactJson(['message' => 'No autenticado.']);
});

it('rechaza con 401 una ruta de administrador sin sesión', function () {
    $this->getJson('/api/_test/admin')->assertUnauthorized();
});

it('rechaza con 403 al usuario en una ruta de administrador', function () {
    $token = tokenParaRol(UserModel::factory()->create());

    $this->withToken($token)->getJson('/api/_test/protegida')->assertOk();
    $this->app['auth']->forgetGuards();
    $this->withToken($token)->getJson('/api/_test/admin')
        ->assertForbidden()
        ->assertExactJson(['message' => 'No tienes permisos para realizar esta acción.']);
});

it('permite al administrador entrar en una ruta de administrador', function () {
    $token = tokenParaRol(UserModel::factory()->admin()->create());

    $this->withToken($token)->getJson('/api/_test/admin')->assertOk();
});
