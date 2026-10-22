<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Las preguntas pasan a colgar directamente de la competencia.
        Schema::table('competencia_preguntas', function (Blueprint $table) {
            $table->foreignId('competencia_id')->nullable()->constrained('competencias')->restrictOnDelete();
        });

        DB::statement('update competencia_preguntas set competencia_id = (select competencia_id from competencia_pregunta_registros where competencia_pregunta_registros.id = competencia_preguntas.registro_id)');

        Schema::table('competencia_preguntas', function (Blueprint $table) {
            $table->dropUnique(['registro_id', 'orden']);
        });

        Schema::table('competencia_preguntas', function (Blueprint $table) {
            $table->dropForeign(['registro_id']);
        });

        Schema::table('competencia_preguntas', function (Blueprint $table) {
            $table->dropColumn('registro_id');
        });

        Schema::table('competencia_preguntas', function (Blueprint $table) {
            $table->unsignedBigInteger('competencia_id')->nullable(false)->change();
            $table->unique(['competencia_id', 'orden']);
        });

        // Las cabeceras de registro ya no existen: la versión y el autor viven en `competencias`.
        Schema::dropIfExists('competencia_pregunta_registros');
        Schema::dropIfExists('competencia_comportamiento_registros');
    }

    public function down(): void
    {
        // La migración traslada datos y elimina tablas: no se revierte.
    }
};
