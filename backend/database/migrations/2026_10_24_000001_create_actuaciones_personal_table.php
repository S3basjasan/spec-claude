<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Columnas selladas al presentar: no cambian nunca (solo `estado`, `version` y `updated_*` admiten cambios). */
    private const SELLADAS = [
        'id', 'tenant_id', 'colaborador_id', 'vinculo_id', 'tipo', 'categoria', 'motivo', 'presentada_por',
        'en_nombre_de_colaborador', 'presentada_en', 'fecha_presentacion', 'politica_version_id', 'politica_numero',
        'politica_condiciones', 'politica_hash', 'operacion_id', 'operacion_hash', 'created_by', 'created_at',
    ];

    public function up(): void
    {
        // SQL crudo: el constructor de esquema no ofrece CHECK entre columnas ni índices únicos parciales.
        DB::statement(<<<'SQL'
            CREATE TABLE actuaciones_personal (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tenant_id INTEGER NOT NULL,
                colaborador_id INTEGER NOT NULL REFERENCES colaboradores (id) ON DELETE RESTRICT,
                vinculo_id INTEGER NOT NULL REFERENCES vinculos_laborales (id) ON DELETE RESTRICT,
                tipo VARCHAR(12) NOT NULL,
                categoria VARCHAR(10) NOT NULL,
                estado VARCHAR(24) NOT NULL,
                motivo VARCHAR(1000) NULL,
                presentada_por INTEGER NOT NULL,
                en_nombre_de_colaborador BOOLEAN NOT NULL DEFAULT 0,
                presentada_en DATETIME NOT NULL,
                fecha_presentacion DATE NOT NULL,
                politica_version_id INTEGER NOT NULL REFERENCES politica_versiones (id) ON DELETE RESTRICT,
                politica_numero INTEGER NOT NULL,
                politica_condiciones TEXT NOT NULL,
                politica_hash CHAR(64) NOT NULL,
                version INTEGER NOT NULL DEFAULT 1,
                operacion_id CHAR(36) NOT NULL,
                operacion_hash CHAR(64) NOT NULL,
                created_by INTEGER NOT NULL,
                updated_by INTEGER NOT NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                CHECK (
                    (categoria = 'Movimiento' AND tipo IN ('Ascenso', 'Traslado')
                        AND estado IN ('Presentada', 'EnRevision', 'Rechazada', 'Aprobada', 'Programada', 'Aplicada', 'IncidenciaDeAplicacion'))
                    OR (categoria = 'Novedad' AND tipo IN ('Permiso', 'Licencia', 'Vacaciones')
                        AND estado IN ('Presentada', 'EnRevision', 'Aprobada', 'Rechazada', 'Retirada'))
                    OR (categoria = 'Novedad' AND tipo = 'Ausencia'
                        AND estado IN ('Informada', 'Revisada', 'Justificada'))
                )
            )
        SQL);

        DB::statement('CREATE UNIQUE INDEX actuaciones_operacion_unica ON actuaciones_personal (tenant_id, operacion_id)');
        DB::statement('CREATE INDEX actuaciones_listado ON actuaciones_personal (tenant_id, estado, tipo, presentada_en)');
        DB::statement('CREATE INDEX actuaciones_por_colaborador ON actuaciones_personal (tenant_id, colaborador_id, presentada_en)');
        // A lo sumo un movimiento aprobado pendiente (programado, vencido o con incidencia) por persona.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX actuaciones_un_movimiento_pendiente ON actuaciones_personal (colaborador_id)
            WHERE categoria = 'Movimiento' AND estado IN ('Aprobada', 'Programada', 'IncidenciaDeAplicacion')
        SQL);

        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        $cambios = implode(' OR ', array_map(fn (string $c) => "OLD.$c IS NOT NEW.$c", self::SELLADAS));
        DB::unprepared("CREATE TRIGGER actuaciones_selladas BEFORE UPDATE ON actuaciones_personal
            WHEN $cambios
            BEGIN
                SELECT RAISE(ABORT, 'ACTUACION_INMUTABLE');
            END");
        DB::unprepared("CREATE TRIGGER actuaciones_sin_eliminar BEFORE DELETE ON actuaciones_personal
            BEGIN
                SELECT RAISE(ABORT, 'ACTUACION_NO_ELIMINABLE');
            END");
        DB::unprepared("CREATE TRIGGER actuaciones_movimiento_con_motivo BEFORE INSERT ON actuaciones_personal
            WHEN NEW.categoria = 'Movimiento' AND (NEW.motivo IS NULL OR trim(NEW.motivo) = '')
            BEGIN
                SELECT RAISE(ABORT, 'MOVIMIENTO_SIN_JUSTIFICACION');
            END");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS actuaciones_selladas');
            DB::unprepared('DROP TRIGGER IF EXISTS actuaciones_sin_eliminar');
            DB::unprepared('DROP TRIGGER IF EXISTS actuaciones_movimiento_con_motivo');
        }
        DB::statement('DROP TABLE IF EXISTS actuaciones_personal');
    }
};
