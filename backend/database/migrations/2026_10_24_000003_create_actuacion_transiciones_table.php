<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE actuacion_transiciones (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tenant_id INTEGER NOT NULL,
                actuacion_id INTEGER NOT NULL REFERENCES actuaciones_personal (id) ON DELETE RESTRICT,
                evento VARCHAR(30) NOT NULL,
                estado_anterior VARCHAR(24) NULL,
                estado_nuevo VARCHAR(24) NOT NULL,
                actor_tipo VARCHAR(8) NOT NULL,
                actor_id INTEGER NULL,
                justificacion VARCHAR(1000) NULL,
                detalle TEXT NULL,
                operacion_id CHAR(36) NULL,
                operacion_hash CHAR(64) NULL,
                created_at DATETIME NOT NULL,
                CHECK (actor_tipo IN ('usuario', 'sistema')),
                CHECK ((actor_tipo = 'sistema') = (actor_id IS NULL)),
                CHECK (evento IN ('Presentada', 'PasoARevision', 'Aprobada', 'Rechazada', 'Retirada', 'Programada',
                    'Aplicada', 'IncidenciaDeAplicacion', 'ReintentoDeAplicacion', 'Revisada', 'Justificada'))
            )
        SQL);
        DB::statement('CREATE INDEX transiciones_por_actuacion ON actuacion_transiciones (actuacion_id, id)');
        DB::statement('CREATE UNIQUE INDEX transiciones_operacion_unica ON actuacion_transiciones (tenant_id, operacion_id) WHERE operacion_id IS NOT NULL');

        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        DB::unprepared("CREATE TRIGGER transiciones_sin_actualizar BEFORE UPDATE ON actuacion_transiciones
            BEGIN
                SELECT RAISE(ABORT, 'TRANSICION_INMUTABLE');
            END");
        DB::unprepared("CREATE TRIGGER transiciones_sin_eliminar BEFORE DELETE ON actuacion_transiciones
            BEGIN
                SELECT RAISE(ABORT, 'TRANSICION_INMUTABLE');
            END");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS transiciones_sin_actualizar');
            DB::unprepared('DROP TRIGGER IF EXISTS transiciones_sin_eliminar');
        }
        DB::statement('DROP TABLE IF EXISTS actuacion_transiciones');
    }
};
