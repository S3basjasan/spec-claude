<?php

namespace Database\Factories;

use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\FormacionAcademicaModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormacionAcademicaModel>
 */
class FormacionAcademicaFactory extends Factory
{
    protected $model = FormacionAcademicaModel::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => 1,
            'colaborador_id' => 1,
            'nivel' => 'Tercer nivel',
            'titulo_programa' => 'Ingeniería de Sistemas',
            'institucion' => 'Universidad de prueba',
            'estado' => 'Completada',
            'fecha_inicio' => null,
            'fecha_finalizacion' => null,
            'observaciones' => null,
            'version' => 1,
            'created_by' => 1,
            'updated_by' => 1,
        ];
    }
}
