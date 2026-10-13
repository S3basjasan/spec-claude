<?php

namespace Database\Factories;

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PosicionModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PuestoCompetenciaModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PuestoFuncionModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PuestoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PuestoRequisitoModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Puesto válido: código PU-NNN, una función Esencial, requisitos de Formación y Experiencia y una competencia activa.
 *
 * @extends Factory<PuestoModel>
 */
class PuestoFactory extends Factory
{
    protected $model = PuestoModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => mb_substr(fake()->unique()->words(3, true), 0, 50),
            'descripcion' => fake()->sentence(8),
            'mision' => fake()->sentence(12),
            'estado' => 'Activo',
            'version' => 1,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn () => ['estado' => 'Inactivo']);
    }

    /** Crea posiciones del puesto con cada estado (tabla mínima de 005). */
    public function conPosiciones(int $ocupadas = 0, int $vacantes = 0, int $suprimidas = 0): static
    {
        return $this->afterCreating(function (PuestoModel $puesto) use ($ocupadas, $vacantes, $suprimidas) {
            foreach (['Ocupada' => $ocupadas, 'Vacante' => $vacantes, 'Suprimida' => $suprimidas] as $estado => $cantidad) {
                for ($i = 0; $i < $cantidad; $i++) {
                    PosicionModel::query()->create(['puesto_id' => $puesto->id, 'estado' => $estado]);
                }
            }
        });
    }

    /**
     * Asigna el código y las listas mínimas válidas, igual que el repositorio.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (PuestoModel $puesto) {
            $puesto->nombre_clave = PuestoModel::claveDeNombre($puesto->nombre);
            $puesto->nombre_busqueda = PuestoModel::busquedaDeTexto($puesto->nombre);
        })->afterCreating(function (PuestoModel $puesto) {
            $puesto->forceFill(['codigo' => sprintf('PU-%03d', $puesto->id)])->save();

            PuestoFuncionModel::query()->create(['puesto_id' => $puesto->id, 'descripcion' => "Función esencial de {$puesto->nombre}.", 'tipo' => 'Esencial', 'orden' => 1]);
            PuestoFuncionModel::query()->create(['puesto_id' => $puesto->id, 'descripcion' => "Función complementaria de {$puesto->nombre}.", 'tipo' => 'Complementaria', 'orden' => 2]);
            PuestoRequisitoModel::query()->create(['puesto_id' => $puesto->id, 'tipo' => 'Formacion', 'descripcion' => 'Título de tercer nivel afín.']);
            PuestoRequisitoModel::query()->create(['puesto_id' => $puesto->id, 'tipo' => 'Experiencia', 'descripcion' => '2 años de experiencia afín.']);
            PuestoCompetenciaModel::query()->create([
                'puesto_id' => $puesto->id,
                'competencia_id' => CompetenciaModel::factory()->create()->id,
                'grado_requerido' => 'B',
            ]);
        });
    }
}
