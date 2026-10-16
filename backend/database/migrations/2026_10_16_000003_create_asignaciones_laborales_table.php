<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // `posicion_id` no lleva clave foránea: la existencia la valida el contrato de Estructura.
        DB::statement(<<<'SQL'
            CREATE TABLE asignaciones_laborales (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tenant_id INTEGER NOT NULL,
                vinculo_id INTEGER NOT NULL REFERENCES vinculos_laborales (id) ON DELETE RESTRICT,
                posicion_id INTEGER NOT NULL,
                fecha_inicio DATE NOT NULL,
                fecha_fin DATE NULL,
                version INTEGER NOT NULL DEFAULT 1,
                created_by INTEGER NOT NULL,
                updated_by INTEGER NOT NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                CHECK (fecha_fin IS NULL OR fecha_fin >= fecha_inicio)
            )
        SQL);

        DB::statement('CREATE UNIQUE INDEX asignaciones_una_abierta_por_posicion ON asignaciones_laborales (posicion_id) WHERE fecha_fin IS NULL');
        DB::statement('CREATE UNIQUE INDEX asignaciones_una_abierta_por_vinculo ON asignaciones_laborales (vinculo_id) WHERE fecha_fin IS NULL');
        DB::statement('CREATE INDEX asignaciones_por_vinculo ON asignaciones_laborales (vinculo_id, fecha_inicio)');
        DB::statement('CREATE INDEX asignaciones_por_posicion ON asignaciones_laborales (posicion_id, fecha_inicio)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS asignaciones_laborales');
    }
};
