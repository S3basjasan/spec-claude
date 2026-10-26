<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Trazabilidad del hecho laboral derivado: una asignación abierta por un movimiento apunta a su actuación.
     * Columna nullable (las filas históricas quedan en NULL) e índice único parcial: una actuación genera a lo sumo una.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE asignaciones_laborales ADD COLUMN actuacion_id INTEGER NULL REFERENCES actuaciones_personal (id) ON DELETE RESTRICT');
        DB::statement('CREATE UNIQUE INDEX asignaciones_una_por_actuacion ON asignaciones_laborales (actuacion_id) WHERE actuacion_id IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS asignaciones_una_por_actuacion');
        DB::statement('ALTER TABLE asignaciones_laborales DROP COLUMN actuacion_id');
    }
};
