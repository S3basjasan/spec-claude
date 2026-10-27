<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Columnas selladas al formalizar: no cambian nunca. */
    private const SELLADAS = [
        'id', 'tenant_id', 'colaborador_id', 'vinculo_id', 'motivo', 'fecha_efectiva', 'politica_version_id',
        'politica_numero', 'politica_condiciones', 'politica_hash', 'formalizado_por', 'formalizado_en',
        'operacion_id', 'operacion_hash', 'created_by', 'created_at',
    ];

    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE offboarding_expedientes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tenant_id INTEGER NOT NULL,
                colaborador_id INTEGER NOT NULL REFERENCES colaboradores (id) ON DELETE RESTRICT,
                vinculo_id INTEGER NOT NULL REFERENCES vinculos_laborales (id) ON DELETE RESTRICT,
                motivo VARCHAR(12) NOT NULL,
                fecha_efectiva DATE NOT NULL,
                estado VARCHAR(24) NOT NULL DEFAULT 'Programada',
                cierre_estado VARCHAR(10) NOT NULL DEFAULT 'Pendiente',
                cierre_por INTEGER NULL,
                cierre_en DATETIME NULL,
                cuenta_estado VARCHAR(10) NOT NULL DEFAULT 'Pendiente',
                politica_version_id INTEGER NOT NULL REFERENCES politica_versiones (id) ON DELETE RESTRICT,
                politica_numero INTEGER NOT NULL,
                politica_condiciones TEXT NOT NULL,
                politica_hash CHAR(64) NOT NULL,
                formalizado_por INTEGER NOT NULL,
                formalizado_en DATETIME NOT NULL,
                cancelacion_por INTEGER NULL,
                cancelacion_en DATETIME NULL,
                cancelacion_razon VARCHAR(1000) NULL,
                aplicada_en DATETIME NULL,
                aplicada_por INTEGER NULL,
                aplicada_por_sistema BOOLEAN NOT NULL DEFAULT 0,
                incidencia_codigo VARCHAR(40) NULL,
                incidencia_detalle VARCHAR(300) NULL,
                intentos_aplicacion INTEGER NOT NULL DEFAULT 0,
                version INTEGER NOT NULL DEFAULT 1,
                operacion_id CHAR(36) NOT NULL,
                operacion_hash CHAR(64) NOT NULL,
                created_by INTEGER NOT NULL,
                updated_by INTEGER NOT NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                CHECK (motivo IN ('Renuncia', 'Terminacion', 'FinContrato', 'Jubilacion')),
                CHECK (estado IN ('Programada', 'Efectiva', 'Cancelada', 'IncidenciaDeAplicacion')),
                CHECK (cierre_estado IN ('Pendiente', 'Cerrado')),
                CHECK (cuenta_estado IN ('Pendiente', 'Coordinada', 'Incidencia')),
                CHECK (cierre_estado = 'Pendiente' OR estado = 'Efectiva'),
                CHECK ((estado = 'Cancelada') = (cancelacion_razon IS NOT NULL)),
                CHECK ((estado = 'IncidenciaDeAplicacion') = (incidencia_codigo IS NOT NULL))
            )
        SQL);

        DB::statement('CREATE UNIQUE INDEX offboarding_operacion_unica ON offboarding_expedientes (tenant_id, operacion_id)');
        DB::statement("CREATE UNIQUE INDEX offboarding_una_salida_viva_por_vinculo ON offboarding_expedientes (vinculo_id) WHERE estado <> 'Cancelada'");
        DB::statement('CREATE INDEX offboarding_listado ON offboarding_expedientes (tenant_id, estado, fecha_efectiva)');
        DB::statement('CREATE INDEX offboarding_por_colaborador ON offboarding_expedientes (tenant_id, colaborador_id)');

        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        $cambios = implode(' OR ', array_map(fn (string $c) => "OLD.$c IS NOT NEW.$c", self::SELLADAS));
        DB::unprepared("CREATE TRIGGER offboarding_expedientes_sellados BEFORE UPDATE ON offboarding_expedientes
            WHEN $cambios
            BEGIN SELECT RAISE(ABORT, 'EXPEDIENTE_INMUTABLE'); END");
        DB::unprepared("CREATE TRIGGER offboarding_expedientes_sin_eliminar BEFORE DELETE ON offboarding_expedientes
            BEGIN SELECT RAISE(ABORT, 'EXPEDIENTE_NO_ELIMINABLE'); END");
        // Una salida efectiva o cancelada es terminal: solo el cierre y la cuenta pueden seguir cambiando.
        DB::unprepared("CREATE TRIGGER offboarding_expedientes_terminales BEFORE UPDATE OF estado ON offboarding_expedientes
            WHEN OLD.estado IN ('Efectiva', 'Cancelada') AND NEW.estado <> OLD.estado
            BEGIN SELECT RAISE(ABORT, 'EXPEDIENTE_TERMINAL'); END");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS offboarding_expedientes_sellados');
            DB::unprepared('DROP TRIGGER IF EXISTS offboarding_expedientes_sin_eliminar');
            DB::unprepared('DROP TRIGGER IF EXISTS offboarding_expedientes_terminales');
        }
        DB::statement('DROP TABLE IF EXISTS offboarding_expedientes');
    }
};
