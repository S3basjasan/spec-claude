<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, array<int, string>> */
    private const ESCALAS = [
        'Letras' => ['A', 'B', 'C', 'D'],
        'Números' => ['Nivel 1', 'Nivel 2', 'Nivel 3', 'Nivel 4'],
        'Categorías' => ['Excelente', 'Muy buena', 'Buena', 'Normal'],
    ];

    public function up(): void
    {
        Schema::create('tipo_grados', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 30)->unique();
            // Un solo tipo puede estar activo: es la escala predeterminada del formulario de creación.
            $table->boolean('activo')->default(false);
        });

        // Índice único parcial: garantiza un solo tipo activo donde el motor lo soporta; la aplicación lo respalda.
        if (in_array(DB::getDriverName(), ['sqlite', 'pgsql'], true)) {
            DB::statement('create unique index tipo_grados_activo_unico on tipo_grados (activo) where activo = true');
        }

        Schema::create('tipo_grado_valores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tipo_grado_id')->constrained('tipo_grados')->restrictOnDelete();
            $table->string('valor', 20);
            $table->unsignedTinyInteger('orden'); // 1 = grado más alto

            $table->unique(['tipo_grado_id', 'valor']);
            $table->unique(['tipo_grado_id', 'orden']);
        });

        foreach (self::ESCALAS as $nombre => $valores) {
            $tipoId = DB::table('tipo_grados')->insertGetId(['nombre' => $nombre, 'activo' => $nombre === 'Letras']);

            foreach ($valores as $indice => $valor) {
                DB::table('tipo_grado_valores')->insert([
                    'tipo_grado_id' => $tipoId,
                    'valor' => $valor,
                    'orden' => $indice + 1,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tipo_grado_valores');
        Schema::dropIfExists('tipo_grados');
    }
};
