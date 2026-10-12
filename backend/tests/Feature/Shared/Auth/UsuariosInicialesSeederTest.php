<?php

use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Database\Seeders\UsuariosInicialesSeeder;
use Illuminate\Support\Facades\Hash;

it('siembra un administrador y un usuario de prueba', function () {
    $this->seed(UsuariosInicialesSeeder::class);

    expect(UserModel::query()->count())->toBe(2)
        ->and(UserModel::query()->where('email', 'admin@hrms.test')->value('role'))->toBe('admin')
        ->and(UserModel::query()->where('email', 'usuario@hrms.test')->value('role'))->toBe('user');
});

it('guarda la contraseña con hash', function () {
    $this->seed(UsuariosInicialesSeeder::class);

    $hash = UserModel::query()->where('email', 'admin@hrms.test')->value('password');

    expect($hash)->not->toBe(config('hrms.seed_password'))
        ->and(Hash::check(config('hrms.seed_password'), $hash))->toBeTrue();
});

it('es idempotente', function () {
    $this->seed(UsuariosInicialesSeeder::class);
    $this->seed(UsuariosInicialesSeeder::class);

    expect(UserModel::query()->count())->toBe(2);
});

it('permite iniciar sesión con los usuarios sembrados', function () {
    $this->seed(UsuariosInicialesSeeder::class);

    $this->postJson('/api/auth/login', ['email' => 'admin@hrms.test', 'password' => config('hrms.seed_password')])
        ->assertOk()->assertJsonPath('data.user.role', 'admin');
    $this->postJson('/api/auth/login', ['email' => 'usuario@hrms.test', 'password' => config('hrms.seed_password')])
        ->assertOk()->assertJsonPath('data.user.role', 'user');
});
