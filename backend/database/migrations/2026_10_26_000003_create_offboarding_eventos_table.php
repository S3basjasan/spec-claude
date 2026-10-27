<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE offboarding_eventos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tenant_id INTEGER NOT NULL,
                expediente_id INTEGER NOT NULL REFERENCES offboarding_expedientes (id) ON DELETE RESTRICT,
                evento VARCHAR(30) NOT NULL,
                actor_tipo VARCHAR(8) NOT NULL,
                actor_id INTEGER NULL,
                detalle TEXT NULL,
                operacion_id CHAR(36) NULL,
                operacion_hash CHAR(64) NULL,
                created_at DATETIME NOT NULL,
                CHECK (actor_tipo IN ('usuario', 'sistema')),
                CHECK ((actor_tipo = 'sistema') = (actor_id IS NULL)),
                CHECK (evento IN ('Formalizada', 'Cancelada', 'TareaCompletada', 'Aplicada', 'IncidenciaDeAplicacion',
                    'ReintentoDeAplicacion', 'CuentaCoordinada', 'CierreConfirmado', 'EntrevistaGuardada',
                    'EntrevistaCompletada', 'EntrevistaNoRealizada', 'ConsultaDeRespuestas'))
            )
        SQL);
        DB::statement('CREATE INDEX offboarding_eventos_por_expediente ON offboarding_eventos (expediente_id, id)');
        DB::statement('CREATE UNIQUE INDEX offboarding_eventos_operacion ON offboarding_eventos (tenant_id, operacion_id) WHERE operacion_id IS NOT NULL');

        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        DB::unprepared("CREATE TRIGGER offboarding_eventos_sin_actualizar BEFORE UPDATE ON offboarding_eventos
            BEGIN SELECT RAISE(ABORT, 'EVENTO_INMUTABLE'); END");
        DB::unprepared("CREATE TRIGGER offboarding_eventos_sin_eliminar BEFORE DELETE ON offboarding_eventos
            BEGIN SELECT RAISE(ABORT, 'EVENTO_INMUTABLE'); END");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS offboarding_eventos_sin_actualizar');
            DB::unprepared('DROP TRIGGER IF EXISTS offboarding_eventos_sin_eliminar');
        }
        DB::statement('DROP TABLE IF EXISTS offboarding_eventos');
    }
};
