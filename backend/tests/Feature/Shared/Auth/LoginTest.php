<?php

use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(function () {
    $this->admin = UserModel::factory()->admin()->create(['email' => 'admin@hrms.test', 'name' => 'Admin']);
    $this->usuario = UserModel::factory()->create(['email' => 'usuario@hrms.test', 'name' => 'Usuario']);
});

it('inicia sesión como administrador y devuelve token, usuario y rol', function () {
    $response = $this->postJson('/api/auth/login', ['email' => 'admin@hrms.test', 'password' => 'password']);

    $response->assertOk()
        ->assertJsonPath('data.user.email', 'admin@hrms.test')
        ->assertJsonPath('data.user.role', 'admin')
        ->assertJsonPath('data.user.name', 'Admin')
        ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'name', 'email', 'role']]])
        ->assertJsonMissingPath('data.user.password');

    $token = PersonalAccessToken::findToken($response->json('data.token'));
    expect($token->abilities)->toBe(['admin'])
        ->and($token->expires_at)->toBeNull();
});

it('inicia sesión como usuario con rol user y sin capacidad admin', function () {
    $response = $this->postJson('/api/auth/login', ['email' => 'usuario@hrms.test', 'password' => 'password']);

    $response->assertOk()->assertJsonPath('data.user.role', 'user');

    $token = PersonalAccessToken::findToken($response->json('data.token'));
    expect($token->abilities)->toBe(['user']);
});

it('acepta el correo con mayúsculas y espacios alrededor', function () {
    $this->postJson('/api/auth/login', ['email' => '  Admin@HRMS.test ', 'password' => 'password'])
        ->assertOk()
        ->assertJsonPath('data.user.email', 'admin@hrms.test');
});

it('rechaza con el mismo 401 una contraseña errónea y un correo inexistente', function () {
    $erronea = $this->postJson('/api/auth/login', ['email' => 'admin@hrms.test', 'password' => 'incorrecta']);
    $inexistente = $this->postJson('/api/auth/login', ['email' => 'nadie@hrms.test', 'password' => 'password']);

    $erronea->assertUnauthorized()->assertExactJson(['message' => 'Correo o contraseña incorrectos.']);
    $inexistente->assertUnauthorized()->assertExactJson(['message' => 'Correo o contraseña incorrectos.']);
});

it('distingue mayúsculas en la contraseña', function () {
    $this->postJson('/api/auth/login', ['email' => 'admin@hrms.test', 'password' => 'PASSWORD'])
        ->assertUnauthorized();
});

it('devuelve 422 con errores por campo si faltan datos', function () {
    $this->postJson('/api/auth/login', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password'])
        ->assertJsonPath('errors.email.0', 'Ingresa tu correo electrónico.')
        ->assertJsonPath('errors.password.0', 'Ingresa tu contraseña.');
});

it('devuelve 422 si el correo tiene un formato inválido', function () {
    $this->postJson('/api/auth/login', ['email' => 'no-es-correo', 'password' => 'x'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email'])
        ->assertJsonMissingValidationErrors(['password']);
});

it('permite varias sesiones simultáneas del mismo usuario', function () {
    $this->postJson('/api/auth/login', ['email' => 'admin@hrms.test', 'password' => 'password'])->assertOk();
    $this->postJson('/api/auth/login', ['email' => 'admin@hrms.test', 'password' => 'password'])->assertOk();

    expect($this->admin->tokens()->count())->toBe(2);
});
