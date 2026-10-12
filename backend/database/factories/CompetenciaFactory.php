<?php

namespace Database\Factories;

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaGradoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompetenciaModel>
 */
class CompetenciaFactory extends Factory
{
    protected $model = CompetenciaModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => mb_substr(fake()->unique()->words(3, true), 0, 50),
            'descripcion' => fake()->sentence(12),
            'tipo' => fake()->randomElement(['Cardinal', 'Especifica']),
            'estado' => 'Activo',
            'version' => 1,
        ];
    }

    public function cardinal(): static
    {
        return $this->state(fn () => ['tipo' => 'Cardinal']);
    }

    public function especifica(): static
    {
        return $this->state(fn () => ['tipo' => 'Especifica']);
    }

    public function inactiva(): static
    {
        return $this->state(fn () => ['estado' => 'Inactivo']);
    }

    /**
     * Asigna el código COMP-NNNN y los cuatro grados, igual que el repositorio.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (CompetenciaModel $competencia) {
            $competencia->nombre_clave = CompetenciaModel::claveDeNombre($competencia->nombre);
        })->afterCreating(function (CompetenciaModel $competencia) {
            $competencia->forceFill(['codigo' => sprintf('COMP-%04d', $competencia->id)])->save();

            foreach (['A', 'B', 'C', 'D'] as $letra) {
                CompetenciaGradoModel::query()->create([
                    'competencia_id' => $competencia->id,
                    'grado' => $letra,
                    'descripcion' => "Descripción del grado $letra de {$competencia->nombre}.",
                ]);
            }
        });
    }
}
