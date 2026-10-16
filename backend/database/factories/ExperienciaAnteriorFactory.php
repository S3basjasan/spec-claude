<?php

namespace Database\Factories;

use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\ExperienciaAnteriorModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExperienciaAnteriorModel>
 */
class ExperienciaAnteriorFactory extends Factory
{
    protected $model = ExperienciaAnteriorModel::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => 1,
            'colaborador_id' => 1,
            'organizacion' => 'Organización de prueba',
            'cargo' => 'Analista',
            'fecha_inicio' => '2019-01-07',
            'fecha_finalizacion' => null,
            'funciones' => null,
            'observaciones' => null,
            'version' => 1,
            'created_by' => 1,
            'updated_by' => 1,
        ];
    }
}
