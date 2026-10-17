<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Amplía la tabla MÍNIMA de micro-contenidos creada por el Catálogo formativo (feature 005); no la recrea.
     * Los ids se conservan para no romper `competencia_microcontenido`. Solo usa renombrar, quitar y añadir
     * columnas (operaciones nativas de SQLite, sin reconstruir la tabla ni tocar sus claves foráneas). SQLite no
     * limita la longitud de las columnas ni cambia el valor por defecto de `estado` (Disponible): el modelo y la
     * fábrica siempre fijan el estado, y el modelo declara `Borrador` como valor por defecto.
     */
    public function up(): void
    {
        Schema::table('microcontenidos', function (Blueprint $table) {
            $table->renameColumn('nombre', 'titulo');
            $table->renameColumn('url_destino', 'url');
        });

        Schema::table('microcontenidos', function (Blueprint $table) {
            $table->dropColumn('formador');
        });

        Schema::table('microcontenidos', function (Blueprint $table) {
            $table->string('codigo', 12)->nullable()->unique(); // MICRO-NNNN; se asigna en la misma transacción del alta
            $table->string('miniatura_url', 500)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
        });

        DB::table('microcontenidos')->where('estado', 'Disponible')->update(['estado' => 'Publicado']);
        DB::table('microcontenidos')->where('tipo', 'Lectura')->update(['tipo' => 'Articulo']);
        DB::table('microcontenidos')->where('tipo', 'Infografia')->update(['tipo' => 'PDF']);
        DB::table('microcontenidos')->where('tipo', 'Audio')->update(['tipo' => 'Video']);

        foreach (DB::table('microcontenidos')->whereNull('codigo')->pluck('id') as $id) {
            DB::table('microcontenidos')->where('id', $id)->update(['codigo' => 'MICRO-'.str_pad((string) $id, 4, '0', STR_PAD_LEFT)]);
        }
    }

    public function down(): void
    {
        DB::table('microcontenidos')->where('estado', 'Publicado')->update(['estado' => 'Disponible']);
        DB::table('microcontenidos')->whereIn('estado', ['Borrador'])->update(['estado' => 'Archivado']);
        DB::table('microcontenidos')->where('tipo', 'Articulo')->update(['tipo' => 'Lectura']);
        DB::table('microcontenidos')->whereIn('tipo', ['PDF', 'Quiz'])->update(['tipo' => 'Infografia']);

        Schema::table('microcontenidos', function (Blueprint $table) {
            $table->dropUnique(['codigo']);
        });

        Schema::table('microcontenidos', function (Blueprint $table) {
            $table->dropColumn(['codigo', 'miniatura_url', 'created_by', 'updated_by']);
        });

        Schema::table('microcontenidos', function (Blueprint $table) {
            $table->string('formador', 80)->nullable();
        });

        Schema::table('microcontenidos', function (Blueprint $table) {
            $table->renameColumn('titulo', 'nombre');
            $table->renameColumn('url', 'url_destino');
        });
    }
};
