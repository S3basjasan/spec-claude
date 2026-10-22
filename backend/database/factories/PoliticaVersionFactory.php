<?php

namespace Database\Factories;

use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\PoliticaVersionModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Borrador sintético; el helper `politicaPublicada()` de las pruebas sella una publicada coherente.
 *
 * @extends Factory<PoliticaVersionModel>
 */
class PoliticaVersionFactory extends Factory
{
    protected $model = PoliticaVersionModel::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => 1,
            'politica_id' => 1,
            'numero' => null,
            'descripcion' => 'Política de prueba',
            'parametros' => '{}',
            'version' => 1,
            'created_by' => 1,
            'updated_by' => 1,
        ];
    }
}
