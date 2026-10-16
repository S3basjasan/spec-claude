<?php

namespace Database\Factories;

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\AreaFormativaModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AreaFormativaModel>
 */
class AreaFormativaFactory extends Factory
{
    protected $model = AreaFormativaModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['nombre' => mb_substr(fake()->unique()->words(2, true), 0, 60)];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (AreaFormativaModel $area) {
            $area->nombre = AreaFormativaModel::normalizarNombre($area->nombre);
            $area->nombre_clave = AreaFormativaModel::claveDeNombre($area->nombre);
        });
    }
}
