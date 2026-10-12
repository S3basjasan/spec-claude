<?php

namespace Database\Seeders;

use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Seeder;

class UsuariosInicialesSeeder extends Seeder
{
    /**
     * Siembra el administrador y el usuario de prueba (idempotente).
     */
    public function run(): void
    {
        $password = (string) config('hrms.seed_password');

        $usuarios = [
            ['name' => 'Administrador de prueba', 'email' => 'admin@hrms.test', 'role' => 'admin'],
            ['name' => 'Usuario de prueba', 'email' => 'usuario@hrms.test', 'role' => 'user'],
        ];

        foreach ($usuarios as $datos) {
            UserModel::query()->updateOrCreate(
                ['email' => $datos['email']],
                ['name' => $datos['name'], 'role' => $datos['role'], 'password' => $password],
            );
        }
    }
}
