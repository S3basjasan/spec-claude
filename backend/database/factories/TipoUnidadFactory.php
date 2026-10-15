<?php

namespace Database\Factories;

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\TipoUnidadModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Tipo de unidad válido: por defecto se agrega al final de la cadena (nivel = máximo + 1).
 *
 * @extends Factory<TipoUnidadModel>
 */
class TipoUnidadFactory extends Factory
{
    protected $model = TipoUnidadModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => mb_substr(fake()->unique()->words(2, true), 0, 50),
            'nivel' => fn () => (int) TipoUnidadModel::query()->max('nivel') + 1,
        ];
    }
}
