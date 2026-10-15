<?php

namespace Database\Factories;

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\CursoModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CursoModel>
 */
class CursoFactory extends Factory
{
    protected $model = CursoModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => mb_substr(fake()->unique()->words(3, true), 0, 120),
            'tipo' => fake()->randomElement(['Online', 'Presencial', 'Mixto']),
            'duracion_minutos' => fake()->numberBetween(30, 600),
            'formador' => fake()->name(),
            'estado' => 'Disponible',
            'url_destino' => null,
        ];
    }

    public function archivado(): static
    {
        return $this->state(fn () => ['estado' => 'Archivado']);
    }

    public function conDestino(string $url = '/cursos/1'): static
    {
        return $this->state(fn () => ['url_destino' => $url]);
    }
}
