<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // `posicion_*_id` no llevan clave foránea: la existencia la valida el contrato de Estructura.
        DB::statement(<<<'SQL'
            CREATE TABLE movimientos_personal (
                actuacion_id INTEGER PRIMARY KEY REFERENCES actuaciones_personal (id) ON DELETE RESTRICT,
                tenant_id INTEGER NOT NULL,
                asignacion_origen_id INTEGER NOT NULL REFERENCES asignaciones_laborales (id) ON DELETE RESTRICT,
                posicion_origen_id INTEGER NOT NULL,
                posicion_destino_id INTEGER NOT NULL,
                fecha_efectiva DATE NOT NULL,
                evaluacion_estado VARCHAR(14) NULL,
                evaluacion_contexto TEXT NULL,
                evaluacion_consultada_en DATETIME NULL,
                aprobada_por INTEGER NULL,
                aprobada_en DATETIME NULL,
                aplicacion_estado VARCHAR(10) NOT NULL DEFAULT 'Pendiente',
                aplicada_en DATETIME NULL,
                aplicada_por INTEGER NULL,
                aplicada_por_sistema BOOLEAN NOT NULL DEFAULT 0,
                asignacion_nueva_id INTEGER NULL REFERENCES asignaciones_laborales (id) ON DELETE RESTRICT,
                incidencia_codigo VARCHAR(40) NULL,
                incidencia_detalle VARCHAR(300) NULL,
                intentos_aplicacion INTEGER NOT NULL DEFAULT 0,
                CHECK (posicion_origen_id <> posicion_destino_id),
                CHECK (aplicacion_estado IN ('Pendiente', 'Aplicada', 'Incidencia')),
                CHECK (evaluacion_estado IS NULL OR evaluacion_estado IN ('Valida', 'NoDisponible', 'NoVerificable')),
                CHECK ((aplicacion_estado = 'Aplicada') = (asignacion_nueva_id IS NOT NULL AND aplicada_en IS NOT NULL)),
                CHECK ((aplicacion_estado = 'Incidencia') = (incidencia_codigo IS NOT NULL))
            )
        SQL);
        DB::statement('CREATE INDEX movimientos_por_aplicar ON movimientos_personal (tenant_id, aplicacion_estado, fecha_efectiva)');
        DB::statement('CREATE INDEX movimientos_por_destino ON movimientos_personal (posicion_destino_id)');

        DB::statement(<<<'SQL'
            CREATE TABLE novedades_personal (
                actuacion_id INTEGER PRIMARY KEY REFERENCES actuaciones_personal (id) ON DELETE RESTRICT,
                tenant_id INTEGER NOT NULL,
                fecha_inicio DATE NOT NULL,
                fecha_fin DATE NOT NULL,
                ocurrida BOOLEAN NOT NULL DEFAULT 0,
                cruce_reconocido_por INTEGER NULL,
                cruce_reconocido_en DATETIME NULL,
                revision_observacion VARCHAR(500) NULL,
                CHECK (fecha_fin >= fecha_inicio)
            )
        SQL);
        DB::statement('CREATE INDEX novedades_por_periodo ON novedades_personal (tenant_id, fecha_inicio, fecha_fin)');

        DB::statement(<<<'SQL'
            CREATE TABLE actuacion_documentos (
                actuacion_id INTEGER NOT NULL REFERENCES actuaciones_personal (id) ON DELETE RESTRICT,
                documento_id INTEGER NOT NULL REFERENCES documentos_colaborador (id) ON DELETE RESTRICT,
                tenant_id INTEGER NOT NULL,
                created_at DATETIME NULL,
                PRIMARY KEY (actuacion_id, documento_id)
            )
        SQL);
        DB::statement('CREATE INDEX actuacion_documentos_por_documento ON actuacion_documentos (documento_id)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS actuacion_documentos');
        DB::statement('DROP TABLE IF EXISTS novedades_personal');
        DB::statement('DROP TABLE IF EXISTS movimientos_personal');
    }
};
