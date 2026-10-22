<?php

namespace Database\Factories;

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicrocontenidoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicroProgresoModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MicroProgresoModel>
 */
class MicroProgresoFactory extends Factory
{
    protected $model = MicroProgresoModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'microcontenido_id' => MicrocontenidoModel::factory()->publicado(),
            'colaborador_id' => fake()->unique()->numberBetween(1, 100000),
            'tenant_id' => 1,
            'estado' => 'EnProgreso',
            'posicion_segundos' => null,
            'fecha_inicio' => now()->subDay(),
            'fecha_completado' => null,
        ];
    }

    public function enProgreso(): static
    {
        return $this->state(fn () => ['estado' => 'EnProgreso', 'fecha_completado' => null]);
    }

    public function completado(): static
    {
        return $this->state(fn () => ['estado' => 'Completado', 'fecha_completado' => now()]);
    }
}
