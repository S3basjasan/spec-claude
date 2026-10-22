<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('puesto_competencias', function (Blueprint $table) {
            $table->foreignId('competencia_grado_id')->nullable()->constrained('competencia_grados')->restrictOnDelete();
        });

        // A→orden 1 … D→orden 4: el grado requerido apunta al grado de la competencia, no a su nombre.
        $letras = ['A' => 1, 'B' => 2, 'C' => 3, 'D' => 4];

        foreach ($letras as $letra => $orden) {
            DB::statement(
                'update puesto_competencias set competencia_grado_id = ('
                .'select competencia_grados.id from competencia_grados '
                .'join tipo_grado_valores on tipo_grado_valores.id = competencia_grados.tipo_grado_valor_id '
                .'where competencia_grados.competencia_id = puesto_competencias.competencia_id and tipo_grado_valores.orden = ?'
                .') where grado_requerido = ?',
                [$orden, $letra],
            );
        }

        Schema::table('puesto_competencias', function (Blueprint $table) {
            $table->dropColumn('grado_requerido');
        });

        Schema::table('puesto_competencias', function (Blueprint $table) {
            $table->unsignedBigInteger('competencia_grado_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        // La migración traslada datos y elimina columnas: no se revierte.
    }
};
