<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\AreaFormativaModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\CursoModel;
use Database\Seeders\CatalogoFormativoSeeder;
use Database\Seeders\CompetenciasSeeder;

it('siembra 4 áreas y deja los casos de prueba de cobertura', function () {
    $this->seed(CompetenciasSeeder::class);
    $this->seed(CatalogoFormativoSeeder::class);
    actuarComoAdmin();

    $cobertura = $this->getJson('/api/catalogo-formativo/cobertura')->json('data');
    $sinOferta = $this->getJson('/api/catalogo-formativo?cobertura=sin_oferta')->json('data');
    $inactivas = $this->getJson('/api/catalogo-formativo?estado=Inactivo')->json('data');

    expect(AreaFormativaModel::query()->count())->toBe(4)
        ->and($cobertura)->toBe(['activas_totales' => 6, 'activas_cubiertas' => 4, 'porcentaje' => 67])
        ->and(count($sinOferta))->toBeGreaterThanOrEqual(2)
        ->and($inactivas[0]['cursos_count'])->toBeGreaterThanOrEqual(1)
        ->and(CursoModel::query()->where('estado', 'Archivado')->count())->toBe(1);
});

it('es idempotente', function () {
    $this->seed(CompetenciasSeeder::class);
    $this->seed(CatalogoFormativoSeeder::class);
    $this->seed(CatalogoFormativoSeeder::class);

    expect(AreaFormativaModel::query()->count())->toBe(4)->and(CursoModel::query()->count())->toBe(8);
});
