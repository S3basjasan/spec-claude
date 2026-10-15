<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Columna reservada para la feature de posiciones: la feature de unidades solo la lee (cuenta las
        // posiciones Ocupadas o Vacantes de cada unidad) y la siembra.
        Schema::table('posiciones', function (Blueprint $table) {
            $table->foreignId('unidad_organizacional_id')
                ->index()
                ->nullable()
                ->constrained('unidades_organizacionales')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posiciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unidad_organizacional_id');
        });
    }
};
