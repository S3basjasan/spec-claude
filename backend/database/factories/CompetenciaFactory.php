<?php

namespace Database\Factories;

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CategoriaModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaGradoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\ComportamientoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PreguntaModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\TipoGradoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\TipoGradoValorModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * Crea competencias completas (4 grados, 16 comportamientos y 4 preguntas) en la escala Letras, como las que
 * produce el diccionario. Los estados `incompleta`, `sinPreguntas`, `sinComportamientos`, `conEscala` y
 * `conCategorias` ajustan el contenido después de crearla.
 *
 * @extends Factory<CompetenciaModel>
 */
class CompetenciaFactory extends Factory
{
    protected $model = CompetenciaModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => mb_substr(fake()->unique()->words(3, true), 0, 50),
            'descripcion' => fake()->sentence(12),
            'tipo' => fake()->randomElement(['Cardinal', 'Especifica']),
            'estado' => 'Activo',
            'version' => 1,
        ];
    }

    public function cardinal(): static
    {
        return $this->state(fn () => ['tipo' => 'Cardinal']);
    }

    public function especifica(): static
    {
        return $this->state(fn () => ['tipo' => 'Especifica']);
    }

    public function inactiva(): static
    {
        return $this->state(fn () => ['estado' => 'Inactivo']);
    }

    /** Sin comportamientos ni preguntas (competencia heredada incompleta). */
    public function incompleta(): static
    {
        return $this->sinComportamientos()->sinPreguntas();
    }

    public function sinPreguntas(): static
    {
        return $this->afterCreating(function (CompetenciaModel $competencia) {
            PreguntaModel::query()->where('competencia_id', $competencia->id)->delete();
        });
    }

    public function sinComportamientos(): static
    {
        return $this->afterCreating(function (CompetenciaModel $competencia) {
            ComportamientoModel::query()
                ->whereIn('competencia_grado_id', CompetenciaGradoModel::query()->where('competencia_id', $competencia->id)->pluck('id'))
                ->delete();
        });
    }

    /** Pasa los grados a otra escala del catálogo (`Números` o `Categorías`). */
    public function conEscala(string $nombre): static
    {
        return $this->afterCreating(function (CompetenciaModel $competencia) use ($nombre) {
            $tipo = TipoGradoModel::query()->where('nombre', $nombre)->firstOrFail();
            $valores = TipoGradoValorModel::query()->where('tipo_grado_id', $tipo->id)->orderBy('orden')->pluck('id')->values();
            $grados = CompetenciaGradoModel::query()->where('competencia_id', $competencia->id)->orderBy('id')->get();

            foreach ($grados as $indice => $grado) {
                $grado->forceFill(['tipo_grado_valor_id' => $valores[$indice]])->save();
            }
        });
    }

    /**
     * Pasa a la escala Categorías con nombres propios.
     *
     * @param  array<int, string>  $nombres  Cuatro nombres.
     */
    public function conCategorias(array $nombres = ['Maestría', 'Avanzado', 'Competente', 'Inicial']): static
    {
        return $this->conEscala('Categorías')->afterCreating(function (CompetenciaModel $competencia) use ($nombres) {
            foreach (array_values($nombres) as $indice => $nombre) {
                CategoriaModel::query()->create([
                    'competencia_id' => $competencia->id,
                    'orden' => $indice + 1,
                    'nombre' => $nombre,
                    'nombre_clave' => CompetenciaModel::claveDeNombre($nombre),
                ]);
            }
        });
    }

    /**
     * Asigna el código COMP-NNNN y crea el contenido completo, igual que el repositorio.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (CompetenciaModel $competencia) {
            $competencia->nombre_clave = CompetenciaModel::claveDeNombre($competencia->nombre);
        })->afterCreating(function (CompetenciaModel $competencia) {
            $competencia->forceFill(['codigo' => sprintf('COMP-%04d', $competencia->id)])->save();

            $letras = DB::table('tipo_grados')->where('nombre', 'Letras')->value('id');
            $valores = DB::table('tipo_grado_valores')->where('tipo_grado_id', $letras)->orderBy('orden')->pluck('id', 'orden');

            $comportamientos = [];
            foreach ([1, 2, 3, 4] as $orden) {
                $gradoId = DB::table('competencia_grados')->insertGetId([
                    'competencia_id' => $competencia->id,
                    'tipo_grado_valor_id' => $valores[$orden],
                    'descripcion' => 'Descripción del grado '.chr(64 + $orden)." de {$competencia->nombre}.",
                ]);

                foreach ([1, 2, 3, 4] as $numero) {
                    $comportamientos[] = [
                        'competencia_grado_id' => $gradoId,
                        'orden' => $numero,
                        'descripcion' => "Comportamiento $numero del grado ".chr(64 + $orden).'.',
                    ];
                }
            }
            DB::table('competencia_comportamientos')->insert($comportamientos);

            DB::table('competencia_preguntas')->insert(array_map(fn (int $orden) => [
                'competencia_id' => $competencia->id,
                'orden' => $orden,
                'pregunta' => "Pregunta $orden sobre {$competencia->nombre}.",
            ], [1, 2, 3, 4]));
        });
    }
}
