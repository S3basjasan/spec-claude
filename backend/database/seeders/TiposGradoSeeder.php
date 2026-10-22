<?php

namespace Database\Seeders;

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\TipoGradoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\TipoGradoValorModel;
use Illuminate\Database\Seeder;

class TiposGradoSeeder extends Seeder
{
    /**
     * Catálogo de escalas con las que se nombran los grados. La migración ya lo siembra: este seeder es
     * idempotente y solo repone lo que falte (Letras es la escala predeterminada).
     */
    public function run(): void
    {
        $escalas = [
            'Letras' => ['A', 'B', 'C', 'D'],
            'Números' => ['Nivel 1', 'Nivel 2', 'Nivel 3', 'Nivel 4'],
            'Categorías' => ['Excelente', 'Muy buena', 'Buena', 'Normal'],
        ];

        foreach ($escalas as $nombre => $valores) {
            $tipo = TipoGradoModel::query()->firstOrCreate(['nombre' => $nombre], ['activo' => $nombre === 'Letras']);

            foreach ($valores as $indice => $valor) {
                TipoGradoValorModel::query()->firstOrCreate(
                    ['tipo_grado_id' => $tipo->id, 'orden' => $indice + 1],
                    ['valor' => $valor],
                );
            }
        }
    }
}
