<?php

namespace Database\Factories;

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicrocontenidoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicroOpcionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicroPreguntaModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MicroPreguntaModel>
 */
class MicroPreguntaFactory extends Factory
{
    protected $model = MicroPreguntaModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'microcontenido_id' => MicrocontenidoModel::factory()->quiz(),
            'enunciado' => rtrim(fake()->sentence(6), '.').'?',
            'orden' => 1,
        ];
    }

    /** Crea las opciones con la correcta en la posición indicada (1 = A). */
    public function conOpciones(int $cantidad = 4, int $correcta = 1): static
    {
        return $this->afterCreating(function (MicroPreguntaModel $pregunta) use ($cantidad, $correcta) {
            for ($i = 1; $i <= $cantidad; $i++) {
                MicroOpcionModel::query()->create([
                    'pregunta_id' => $pregunta->id,
                    'texto' => 'Opción '.$i,
                    'es_correcta' => $i === $correcta,
                    'orden' => $i,
                ]);
            }
        });
    }
}
