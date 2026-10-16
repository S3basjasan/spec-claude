<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\DnfDetalleModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\DnfModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Database\Seeders\CompetenciasSeeder;
use Database\Seeders\DnfSeeder;
use Database\Seeders\PersonalSeeder;
use Database\Seeders\PuestosSeeder;
use Database\Seeders\TiposUnidadSeeder;
use Database\Seeders\UnidadesSeeder;
use Database\Seeders\UsuariosInicialesSeeder;

function sembrarBaseDeDnf(): void
{
    foreach ([UsuariosInicialesSeeder::class, CompetenciasSeeder::class, PuestosSeeder::class, TiposUnidadSeeder::class, UnidadesSeeder::class, PersonalSeeder::class] as $seeder) {
        test()->seed($seeder);
    }
}

it('siembra una DNF individual y una por departamento con todas las prioridades y estados', function () {
    sembrarBaseDeDnf();

    $this->seed(DnfSeeder::class);

    $dnfs = DnfModel::query()->orderBy('id')->get();
    $admin = UserModel::query()->where('email', 'admin@hrms.test')->value('id');
    expect($dnfs)->toHaveCount(2)
        ->and($dnfs[0]->alcance)->toBe('Individual')
        ->and($dnfs[0]->codigo)->toBe('DNF-0001')
        ->and($dnfs[1]->alcance)->toBe('Departamento')
        ->and($dnfs[1]->departamento_id)->not->toBeNull()
        ->and((int) $dnfs[0]->created_by)->toBe((int) $admin);

    $detalles = DnfDetalleModel::query()->get();
    expect($detalles->pluck('prioridad')->filter()->unique()->sort()->values()->all())->toBe(['Alta', 'Baja', 'Critica', 'Media'])
        ->and($detalles->pluck('estado')->unique()->all())->toContain('Cumple', 'SinEvaluacion', 'ConBrecha');
});

it('es idempotente', function () {
    sembrarBaseDeDnf();

    $this->seed(DnfSeeder::class);
    $this->seed(DnfSeeder::class);

    expect(DnfModel::query()->count())->toBe(2);
});

it('no siembra nada si faltan los datos de los que depende', function () {
    $this->seed(DnfSeeder::class);

    expect(DnfModel::query()->count())->toBe(0);
});
