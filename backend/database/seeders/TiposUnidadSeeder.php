<?php

namespace Database\Seeders;

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\TipoUnidadModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Seeder;

class TiposUnidadSeeder extends Seeder
{
    /**
     * Cadena de tipos de unidad, idempotente por nivel. Los niveles son continuos: Gerencia General (1, tipo raíz),
     * Gerencia (2), Área (3), Departamento (4) y Sección (5). Después se gestionan desde la aplicación.
     */
    public function run(): void
    {
        $creadoPor = UserModel::query()->where('email', 'admin@hrms.test')->value('id');

        foreach (['Gerencia General', 'Gerencia', 'Área', 'Departamento', 'Sección'] as $indice => $nombre) {
            TipoUnidadModel::query()->firstOrCreate(
                ['nivel' => $indice + 1],
                ['nombre' => $nombre, 'created_by' => $creadoPor === null ? null : (int) $creadoPor],
            );
        }
    }
}
