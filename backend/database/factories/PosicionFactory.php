<?php

namespace Database\Factories;

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PosicionModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PuestoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\UnidadModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Posición válida: Vacante, con puesto y unidad activos y modalidad Contrato. Por defecto reporta a la máxima autoridad
 * vigente (la crea si no existe); el código `POS-####` se asigna del `id`.
 *
 * @extends Factory<PosicionModel>
 */
class PosicionFactory extends Factory
{
    protected $model = PosicionModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'puesto_id' => fn () => PuestoModel::factory()->create()->id,
            'unidad_organizacional_id' => fn () => UnidadModel::factory()->create()->id,
            'posicion_jefe_id' => fn () => PosicionModel::query()->where('estado', '<>', 'Suprimida')->whereNull('posicion_jefe_id')->value('id')
                ?? PosicionModel::factory()->maximaAutoridad()->create()->id,
            'estado' => 'Vacante',
            'modalidad' => 'Contrato',
            'version' => 1,
        ];
    }

    public function maximaAutoridad(): static
    {
        return $this->state(fn () => ['posicion_jefe_id' => null]);
    }

    public function conSupervisor(PosicionModel|int $supervisor): static
    {
        return $this->state(fn () => ['posicion_jefe_id' => $supervisor instanceof PosicionModel ? $supervisor->id : $supervisor]);
    }

    public function ocupada(?UserModel $ocupante = null): static
    {
        return $this->state(fn () => ['estado' => 'Ocupada'])->afterCreating(function (PosicionModel $posicion) use ($ocupante) {
            $usuario = $ocupante ?? UserModel::factory()->create();
            $posicion->forceFill(['user_id' => $usuario->id])->saveQuietly();
        });
    }

    public function suprimida(): static
    {
        return $this->state(fn () => ['estado' => 'Suprimida']);
    }

    public function configure(): static
    {
        return $this->afterCreating(function (PosicionModel $posicion) {
            if ($posicion->codigo === null) {
                $posicion->forceFill(['codigo' => PosicionModel::codigoDeId($posicion->id)])->saveQuietly();
            }
        });
    }
}
