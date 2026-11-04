<?php

namespace Database\Factories;

use App\Modules\Compensaciones\Domain\ValueObjects\Icono;
use App\Modules\Compensaciones\Domain\ValueObjects\Normalizador;
use App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Models\CategoriaReconocimientoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategoriaReconocimientoModel>
 */
class CategoriaReconocimientoFactory extends Factory
{
    protected $model = CategoriaReconocimientoModel::class;

    private static int $secuencia = 0;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        self::$secuencia++;

        return [
            'codigo' => 'CAT-'.str_pad((string) self::$secuencia, 3, '0', STR_PAD_LEFT),
            'nombre' => 'Categoría '.fake()->unique()->words(2, true).' '.self::$secuencia,
            'descripcion' => fake()->sentence(10),
            'competencia_id' => CompetenciaModel::factory(),
            'puntuacion_base' => fake()->numberBetween(10, 500),
            'icono' => fake()->randomElement(Icono::valores()),
            'estado' => 'Activo',
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (CategoriaReconocimientoModel $categoria) {
            $categoria->nombre_normalizado = Normalizador::normalizar($categoria->nombre);

            // `indicadores_a_crear` es un atributo temporal de los estados; si la prueba fija `indicadores`, se respeta.
            $cantidad = (int) ($categoria->getAttribute('indicadores_a_crear') ?? 2);
            $categoria->offsetUnset('indicadores_a_crear');

            if (! array_key_exists('indicadores', $categoria->getAttributes())) {
                $categoria->indicadores = array_map(
                    fn (int $orden) => "Indicador $orden de {$categoria->codigo}",
                    $cantidad > 0 ? range(1, $cantidad) : [],
                );
            }
        });
    }

    public function inactiva(): static
    {
        return $this->state(fn () => ['estado' => 'Inactivo']);
    }

    public function conIndicadores(int $cantidad): static
    {
        return $this->state(fn () => ['indicadores_a_crear' => $cantidad]);
    }

    public function conCompetenciaInactiva(): static
    {
        return $this->state(fn () => ['competencia_id' => CompetenciaModel::factory()->inactiva()]);
    }
}
