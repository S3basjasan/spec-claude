<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Cada tenant tiene su propia máxima autoridad (GP-02, RN04): el índice parcial de 006 era global y solo admitía
        // una posición vigente sin supervisor en toda la base. Ahora es una por tenant.
        DB::statement('drop index if exists posiciones_una_maxima_autoridad');
        DB::statement(
            "create unique index posiciones_una_maxima_autoridad on posiciones (tenant_id) where posicion_jefe_id is null and estado <> 'Suprimida'"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('drop index if exists posiciones_una_maxima_autoridad');
        DB::statement(
            "create unique index posiciones_una_maxima_autoridad on posiciones ((1)) where posicion_jefe_id is null and estado <> 'Suprimida'"
        );
    }
};
