<?php

namespace Database\Factories;

use App\Modules\GestionPersonal\Domain\Services\NormalizadorDeTexto;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\ColaboradorModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ColaboradorModel>
 */
class ColaboradorFactory extends Factory
{
    protected $model = ColaboradorModel::class;

    /**
     * Ficha mínima sintética; las claves de identificación y búsqueda se derivan en configure().
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => 1,
            'codigo' => 'EMP-F'.Str::upper(Str::random(8)),
            'tipo_identificacion' => 'Cedula',
            'numero_identificacion' => (string) fake()->unique()->numerify('##########'),
            'identificacion_clave' => '',
            'nombres' => fake()->firstName(),
            'apellidos' => fake()->lastName(),
            'nombres_busqueda' => '',
            'apellidos_busqueda' => '',
            'version' => 1,
            'operacion_id' => (string) Str::uuid(),
            'operacion_hash' => hash('sha256', (string) Str::uuid()),
            'created_by' => 1,
            'updated_by' => 1,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (ColaboradorModel $modelo) {
            $normalizador = new NormalizadorDeTexto;
            $modelo->identificacion_clave = $normalizador->claveDeIdentificacion((string) $modelo->numero_identificacion);
            $modelo->nombres_busqueda = $normalizador->busquedaDeTexto((string) $modelo->nombres);
            $modelo->apellidos_busqueda = $normalizador->busquedaDeTexto((string) $modelo->apellidos);
        });
    }
}
