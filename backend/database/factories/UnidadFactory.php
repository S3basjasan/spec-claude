<?php

namespace Database\Factories;

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\TipoUnidadModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\UnidadModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Unidad válida y activa. Por defecto cuelga de la raíz activa (la crea si no existe) y su tipo es el del nivel
 * siguiente al de su padre (se crea si falta en la cadena); el código `UO-NNN` se asigna del `id`.
 *
 * @extends Factory<UnidadModel>
 */
class UnidadFactory extends Factory
{
    protected $model = UnidadModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => mb_substr(fake()->unique()->words(2, true), 0, 150),
            'unidad_padre_id' => fn () => UnidadModel::query()->where('estado', 'Activa')->whereNull('unidad_padre_id')->value('id')
                ?? UnidadModel::factory()->raiz()->create()->id,
            'tipo_unidad_id' => function (array $atributos) {
                $padre = $atributos['unidad_padre_id'] === null ? null : UnidadModel::query()->with('tipo')->find($atributos['unidad_padre_id']);

                return self::tipoDeNivel($padre === null ? 1 : (int) $padre->tipo->nivel + 1)->id;
            },
            'estado' => 'Activa',
            'fecha_creacion' => now()->toDateString(),
            'fecha_supresion' => null,
            'version' => 1,
        ];
    }

    /** Tipo del nivel dado de la cadena; si falta se crea (niveles anteriores incluidos, para que sea continua). */
    public static function tipoDeNivel(int $nivel): TipoUnidadModel
    {
        for ($n = 1; $n <= $nivel; $n++) {
            TipoUnidadModel::query()->firstOrCreate(['nivel' => $n], ['nombre' => self::nombreDeNivel($n)]);
        }

        return TipoUnidadModel::query()->where('nivel', $nivel)->firstOrFail();
    }

    private static function nombreDeNivel(int $nivel): string
    {
        return ['Gerencia General', 'Gerencia', 'Área', 'Departamento', 'Sección'][$nivel - 1] ?? "Tipo $nivel";
    }

    public function raiz(): static
    {
        return $this->state(fn () => ['unidad_padre_id' => null, 'tipo_unidad_id' => self::tipoDeNivel(1)->id]);
    }

    public function conPadre(UnidadModel|int $padre): static
    {
        return $this->state(fn () => ['unidad_padre_id' => $padre instanceof UnidadModel ? $padre->id : $padre]);
    }

    public function inactiva(): static
    {
        return $this->state(fn () => ['estado' => 'Inactiva', 'fecha_supresion' => now()->toDateString()]);
    }

    public function configure(): static
    {
        return $this->afterCreating(function (UnidadModel $unidad) {
            if ($unidad->codigo === null) {
                $unidad->forceFill(['codigo' => UnidadModel::codigoDeId($unidad->id)])->saveQuietly();
            }
        });
    }
}
