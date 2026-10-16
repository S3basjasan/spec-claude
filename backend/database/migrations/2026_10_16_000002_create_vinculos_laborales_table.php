<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQL crudo: el constructor de esquema no ofrece CHECK ni índices únicos parciales.
        DB::statement(<<<'SQL'
            CREATE TABLE vinculos_laborales (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tenant_id INTEGER NOT NULL,
                colaborador_id INTEGER NOT NULL REFERENCES colaboradores (id) ON DELETE RESTRICT,
                fecha_ingreso DATE NOT NULL,
                fecha_salida DATE NULL,
                tipo_contrato_clave VARCHAR(80) NOT NULL,
                version INTEGER NOT NULL DEFAULT 1,
                operacion_id CHAR(36) NOT NULL,
                operacion_hash CHAR(64) NOT NULL,
                created_by INTEGER NOT NULL,
                updated_by INTEGER NOT NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                CHECK (fecha_salida IS NULL OR fecha_salida >= fecha_ingreso)
            )
        SQL);

        DB::statement('CREATE UNIQUE INDEX vinculos_operacion_unica ON vinculos_laborales (tenant_id, operacion_id)');
        DB::statement('CREATE UNIQUE INDEX vinculos_un_abierto_por_ficha ON vinculos_laborales (colaborador_id) WHERE fecha_salida IS NULL');
        DB::statement('CREATE INDEX vinculos_por_colaborador ON vinculos_laborales (colaborador_id)');
        DB::statement('CREATE INDEX vinculos_por_tenant_ficha_ingreso ON vinculos_laborales (tenant_id, colaborador_id, fecha_ingreso)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS vinculos_laborales');
    }
};
