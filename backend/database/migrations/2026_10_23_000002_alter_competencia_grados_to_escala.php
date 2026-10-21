<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competencia_grados', function (Blueprint $table) {
            $table->foreignId('tipo_grado_valor_id')->nullable()->constrained('tipo_grado_valores')->restrictOnDelete();
            // Ids de usuario sin clave foránea: Estructura no accede a las tablas de otro módulo.
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        // Los grados existentes pasan a la escala Letras: A→orden 1 … D→orden 4.
        $letras = DB::table('tipo_grados')->where('nombre', 'Letras')->value('id');
        $valores = DB::table('tipo_grado_valores')->where('tipo_grado_id', $letras)->pluck('id', 'valor');

        foreach ($valores as $letra => $valorId) {
            DB::table('competencia_grados')->where('grado', $letra)->update(['tipo_grado_valor_id' => $valorId]);
        }

        Schema::table('competencia_grados', function (Blueprint $table) {
            $table->dropUnique(['competencia_id', 'grado']);
        });

        Schema::table('competencia_grados', function (Blueprint $table) {
            $table->dropColumn('grado');
        });

        Schema::table('competencia_grados', function (Blueprint $table) {
            $table->unsignedBigInteger('tipo_grado_valor_id')->nullable(false)->change();
            $table->unique(['competencia_id', 'tipo_grado_valor_id']);
        });
    }

    public function down(): void
    {
        // La migración traslada datos y elimina columnas: no se revierte.
    }
};
