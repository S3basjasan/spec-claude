<?php

namespace Database\Factories;

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PreguntaModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\RegistroPreguntasModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Cabecera del registro de preguntas. Para que cumpla la invariante (exactamente cuatro preguntas)
 * hay que encadenar `conPreguntas()`.
 *
 * @extends Factory<RegistroPreguntasModel>
 */
class RegistroPreguntasFactory extends Factory
{
    protected $model = RegistroPreguntasModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'competencia_id' => CompetenciaModel::factory(),
            'version' => 1,
        ];
    }

    /**
     * Crea las cuatro preguntas del registro (por defecto con textos de ejemplo).
     *
     * @param  array<int, string>|null  $textos  Cuatro textos, en el orden 1 a 4.
     */
    public function conPreguntas(?array $textos = null): static
    {
        return $this->afterCreating(function (RegistroPreguntasModel $registro) use ($textos) {
            foreach (range(1, 4) as $orden) {
                PreguntaModel::query()->create([
                    'registro_id' => $registro->id,
                    'orden' => $orden,
                    'pregunta' => $textos[$orden - 1] ?? "Pregunta $orden de la competencia {$registro->competencia_id}",
                    'created_by' => $registro->created_by,
                ]);
            }
        });
    }
}
