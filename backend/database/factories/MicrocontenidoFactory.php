<?php

namespace Database\Factories;

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicrocontenidoModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MicrocontenidoModel>
 */
class MicrocontenidoFactory extends Factory
{
    protected $model = MicrocontenidoModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => mb_substr(fake()->unique()->words(3, true), 0, 120),
            'tipo' => fake()->randomElement(['Video', 'Lectura', 'Infografia', 'Audio']),
            'duracion_minutos' => fake()->numberBetween(2, 30),
            'formador' => fake()->name(),
            'estado' => 'Disponible',
            'url_destino' => null,
        ];
    }

    public function archivado(): static
    {
        return $this->state(fn () => ['estado' => 'Archivado']);
    }
}
