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
        $tipo = fake()->randomElement(['Video', 'PDF', 'Articulo']);

        return [
            'titulo' => mb_substr(fake()->unique()->words(3, true), 0, 120),
            'tipo' => $tipo,
            'duracion_minutos' => fake()->numberBetween(2, 30),
            'url' => 'https://contenido.test/'.fake()->unique()->slug(2),
            'miniatura_url' => null,
            'estado' => 'Borrador',
        ];
    }

    /** Asigna el código MICRO-NNNN a partir del id, igual que el alta real. */
    public function configure(): static
    {
        return $this->afterCreating(function (MicrocontenidoModel $micro) {
            if ($micro->codigo === null) {
                $micro->forceFill(['codigo' => 'MICRO-'.str_pad((string) $micro->id, 4, '0', STR_PAD_LEFT)])->save();
            }
        });
    }

    public function publicado(): static
    {
        return $this->state(fn () => ['estado' => 'Publicado']);
    }

    public function archivado(): static
    {
        return $this->state(fn () => ['estado' => 'Archivado']);
    }

    public function video(): static
    {
        return $this->state(fn () => ['tipo' => 'Video']);
    }

    public function quiz(): static
    {
        return $this->state(fn () => ['tipo' => 'Quiz', 'url' => null]);
    }
}
