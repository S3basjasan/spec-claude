<?php

namespace Database\Factories;

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaGradoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\ComportamientoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\RegistroComportamientosModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Cabecera del registro de comportamientos. Para que cumpla la invariante (1 a 4 comportamientos por grado)
 * hay que encadenar `conComportamientos()`; la competencia debe existir (con sus cuatro grados).
 *
 * @extends Factory<RegistroComportamientosModel>
 */
class RegistroComportamientosFactory extends Factory
{
    protected $model = RegistroComportamientosModel::class;

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
     * Crea los comportamientos de cada grado.
     *
     * @param  array<string, int>  $conteos  Cantidad por letra A–D (1 a 4).
     */
    public function conComportamientos(array $conteos = ['A' => 2, 'B' => 2, 'C' => 2, 'D' => 2]): static
    {
        return $this->afterCreating(function (RegistroComportamientosModel $registro) use ($conteos) {
            foreach ($conteos as $letra => $cantidad) {
                $grado = CompetenciaGradoModel::query()
                    ->where('competencia_id', $registro->competencia_id)
                    ->where('grado', $letra)
                    ->firstOrFail();

                for ($orden = 1; $orden <= $cantidad; $orden++) {
                    ComportamientoModel::query()->create([
                        'competencia_grado_id' => $grado->id,
                        'orden' => $orden,
                        'descripcion' => "Comportamiento $orden del grado $letra",
                        'created_by' => $registro->created_by,
                    ]);
                }
            }
        });
    }
}
