<?php

use App\Modules\Compensaciones\Domain\ValueObjects\Normalizador;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los indicadores pasan a ser una lista ordenada (JSON) dentro de la categoría y `competencia_id` deja de tener
     * llave foránea: la competencia se valida solo por la Facade de Estructura. No se revalida ni se descarta ningún
     * dato: se copia lo que haya, en orden; una categoría sin filas queda con `[]`.
     */
    public function up(): void
    {
        Schema::table('categorias_reconocimiento', function (Blueprint $table) {
            $table->json('indicadores')->nullable();
        });

        foreach (DB::table('categorias_reconocimiento')->pluck('id') as $id) {
            $textos = DB::table('indicadores_reconocimiento')
                ->where('categoria_reconocimiento_id', $id)
                ->orderBy('orden')
                ->orderBy('id')
                ->pluck('texto')
                ->all();

            DB::table('categorias_reconocimiento')
                ->where('id', $id)
                ->update(['indicadores' => json_encode($textos, JSON_UNESCAPED_UNICODE)]);
        }

        Schema::table('categorias_reconocimiento', function (Blueprint $table) {
            $table->dropForeign(['competencia_id']);
        });

        Schema::drop('indicadores_reconocimiento');
    }

    /**
     * Solo en el repositorio de prueba. Lee `competencias` únicamente para no restaurar la llave original sobre
     * competencias inexistentes; si las hay, aborta sin cambiar nada.
     */
    public function down(): void
    {
        $huerfanas = DB::table('categorias_reconocimiento')
            ->whereNotIn('competencia_id', DB::table('competencias')->select('id'))
            ->pluck('id')
            ->all();

        if ($huerfanas !== []) {
            throw new RuntimeException(
                'No se puede revertir: las categorías con id '.implode(', ', $huerfanas).' apuntan a competencias que ya no existen.'
            );
        }

        Schema::create('indicadores_reconocimiento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categoria_reconocimiento_id')->constrained('categorias_reconocimiento')->restrictOnDelete();
            $table->unsignedSmallInteger('orden'); // 1 a 5, consecutivo
            $table->string('texto', 250);
            $table->string('texto_normalizado', 250);
            $table->timestamps();

            $table->unique(['categoria_reconocimiento_id', 'orden'], 'indicadores_reconocimiento_orden_unico');
            $table->unique(['categoria_reconocimiento_id', 'texto_normalizado'], 'indicadores_reconocimiento_texto_unico');
        });

        $ahora = now();

        foreach (DB::table('categorias_reconocimiento')->select('id', 'indicadores')->get() as $categoria) {
            $textos = json_decode((string) $categoria->indicadores, true) ?: [];

            foreach (array_values($textos) as $posicion => $texto) {
                DB::table('indicadores_reconocimiento')->insert([
                    'categoria_reconocimiento_id' => $categoria->id,
                    'orden' => $posicion + 1,
                    'texto' => $texto,
                    'texto_normalizado' => Normalizador::normalizar($texto),
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }
        }

        Schema::table('categorias_reconocimiento', function (Blueprint $table) {
            $table->dropColumn('indicadores');
        });

        Schema::table('categorias_reconocimiento', function (Blueprint $table) {
            $table->foreign('competencia_id')->references('id')->on('competencias')->restrictOnDelete();
        });
    }
};
