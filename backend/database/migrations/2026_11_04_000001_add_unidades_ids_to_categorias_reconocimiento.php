<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 01b.4: cada categoría se asigna a una o varias unidades. La lista vive en una columna JSON (relación lógica con
 * `unidades_organizacionales` de Estructura, sin FK). La migración solo agrega la columna vacía: no lee datos de otros
 * módulos. Las categorías ya existentes se asignan con `php artisan compensaciones:asignar-unidades-iniciales`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categorias_reconocimiento', function (Blueprint $table) {
            $table->json('unidades_ids')->nullable()->after('indicadores');
        });
    }

    public function down(): void
    {
        Schema::table('categorias_reconocimiento', function (Blueprint $table) {
            $table->dropColumn('unidades_ids');
        });
    }
};
