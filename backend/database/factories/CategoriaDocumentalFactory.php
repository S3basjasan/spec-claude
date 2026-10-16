<?php

namespace Database\Factories;

use App\Modules\GestionPersonal\Domain\Services\NormalizadorDeTexto;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\CategoriaDocumentalModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategoriaDocumentalModel>
 */
class CategoriaDocumentalFactory extends Factory
{
    protected $model = CategoriaDocumentalModel::class;

    /**
     * Categoría sintética; las claves de unicidad y búsqueda se derivan en configure().
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => 1,
            'nombre' => 'Categoría '.fake()->unique()->numerify('######'),
            'nombre_clave' => '',
            'nombre_busqueda' => '',
            'estado' => 'Activa',
            'version' => 1,
            'created_by' => 1,
            'updated_by' => 1,
        ];
    }

    public function inactiva(): static
    {
        return $this->state(fn () => ['estado' => 'Inactiva']);
    }

    public function configure(): static
    {
        return $this->afterMaking(function (CategoriaDocumentalModel $modelo) {
            $normalizador = new NormalizadorDeTexto;
            $modelo->nombre_clave = $normalizador->claveDeNombre((string) $modelo->nombre);
            $modelo->nombre_busqueda = $normalizador->busquedaDeTexto((string) $modelo->nombre);
        });
    }
}
