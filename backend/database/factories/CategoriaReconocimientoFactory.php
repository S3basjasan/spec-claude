<?php

namespace Database\Factories;

use App\Modules\Compensaciones\Domain\ValueObjects\Icono;
use App\Modules\Compensaciones\Domain\ValueObjects\Normalizador;
use App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Models\CategoriaReconocimientoModel;
use App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Models\IndicadorReconocimientoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategoriaReconocimientoModel>
 */
class CategoriaReconocimientoFactory extends Factory
{
    protected $model = CategoriaReconocimientoModel::class;

    private static int $secuencia = 0;

    /** Cantidad de indicadores a crear tras guardar cada categoría (por defecto 2). */
    private static ?\WeakMap $pendientes = null;

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
        return $this
            ->afterMaking(function (CategoriaReconocimientoModel $categoria) {
                $categoria->nombre_normalizado = Normalizador::normalizar($categoria->nombre);

                self::$pendientes ??= new \WeakMap;
                self::$pendientes[$categoria] = (int) ($categoria->getAttribute('indicadores_a_crear') ?? 2);
                $categoria->offsetUnset('indicadores_a_crear');
            })
            ->afterCreating(function (CategoriaReconocimientoModel $categoria) {
                $cantidad = self::$pendientes[$categoria] ?? 2;

                for ($orden = 1; $orden <= $cantidad; $orden++) {
                    $texto = "Indicador $orden de {$categoria->codigo}";

                    IndicadorReconocimientoModel::query()->create([
                        'categoria_reconocimiento_id' => $categoria->id,
                        'orden' => $orden,
                        'texto' => $texto,
                        'texto_normalizado' => Normalizador::normalizar($texto),
                    ]);
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
