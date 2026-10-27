<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE offboarding_tareas (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tenant_id INTEGER NOT NULL,
                expediente_id INTEGER NOT NULL REFERENCES offboarding_expedientes (id) ON DELETE RESTRICT,
                orden INTEGER NOT NULL,
                tipo VARCHAR(10) NOT NULL,
                nombre VARCHAR(150) NOT NULL,
                descripcion VARCHAR(500) NULL,
                exige_evidencia BOOLEAN NOT NULL DEFAULT 0,
                estado VARCHAR(10) NOT NULL DEFAULT 'Pendiente',
                completada_por INTEGER NULL,
                completada_en DATETIME NULL,
                CHECK (tipo IN ('Plantilla', 'Entrevista')),
                CHECK (estado IN ('Pendiente', 'Completada')),
                CHECK ((estado = 'Completada') = (completada_en IS NOT NULL))
            )
        SQL);
        DB::statement('CREATE UNIQUE INDEX offboarding_tareas_orden ON offboarding_tareas (expediente_id, orden)');

        DB::statement(<<<'SQL'
            CREATE TABLE offboarding_documentos (
                expediente_id INTEGER NOT NULL REFERENCES offboarding_expedientes (id) ON DELETE RESTRICT,
                tarea_id INTEGER NOT NULL DEFAULT 0,
                documento_id INTEGER NOT NULL REFERENCES documentos_colaborador (id) ON DELETE RESTRICT,
                tenant_id INTEGER NOT NULL,
                created_at DATETIME NULL,
                PRIMARY KEY (expediente_id, tarea_id, documento_id)
            )
        SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE offboarding_entrevistas (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tenant_id INTEGER NOT NULL,
                expediente_id INTEGER NOT NULL REFERENCES offboarding_expedientes (id) ON DELETE RESTRICT,
                colaborador_id INTEGER NOT NULL,
                cuestionario_version INTEGER NOT NULL,
                estado VARCHAR(12) NOT NULL DEFAULT 'Pendiente',
                presentada_en DATETIME NULL,
                finalizada_en DATETIME NULL,
                motivo_no_realizada VARCHAR(14) NULL,
                no_realizada_por INTEGER NULL,
                no_realizada_en DATETIME NULL,
                version INTEGER NOT NULL DEFAULT 1,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                CHECK (estado IN ('Pendiente', 'EnCurso', 'Completada', 'NoRealizada')),
                CHECK (motivo_no_realizada IS NULL OR motivo_no_realizada IN ('Declinada', 'SinRespuesta', 'SinAcceso')),
                CHECK ((estado = 'Completada') = (presentada_en IS NOT NULL)),
                CHECK ((estado = 'NoRealizada') = (motivo_no_realizada IS NOT NULL))
            )
        SQL);
        DB::statement('CREATE UNIQUE INDEX offboarding_entrevista_por_expediente ON offboarding_entrevistas (expediente_id)');
        DB::statement('CREATE INDEX offboarding_entrevistas_estado ON offboarding_entrevistas (tenant_id, estado)');

        DB::statement(<<<'SQL'
            CREATE TABLE offboarding_respuestas (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tenant_id INTEGER NOT NULL,
                entrevista_id INTEGER NOT NULL REFERENCES offboarding_entrevistas (id) ON DELETE RESTRICT,
                pregunta_id VARCHAR(40) NOT NULL,
                tipo VARCHAR(10) NOT NULL,
                dimension_o_categoria VARCHAR(60) NOT NULL,
                valor INTEGER NULL,
                texto TEXT NULL,
                cuestionario_version INTEGER NOT NULL,
                created_at DATETIME NULL,
                CHECK (tipo IN ('valoracion', 'categoria', 'abierta')),
                CHECK (valor IS NULL OR (valor BETWEEN 1 AND 5)),
                CHECK ((tipo = 'valoracion') = (valor IS NOT NULL)),
                CHECK ((tipo = 'abierta') = (texto IS NOT NULL))
            )
        SQL);
        DB::statement('CREATE UNIQUE INDEX offboarding_respuesta_unica ON offboarding_respuestas (entrevista_id, pregunta_id)');

        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        DB::unprepared("CREATE TRIGGER offboarding_tareas_selladas BEFORE UPDATE ON offboarding_tareas
            WHEN OLD.expediente_id IS NOT NEW.expediente_id OR OLD.orden IS NOT NEW.orden OR OLD.tipo IS NOT NEW.tipo
                OR OLD.nombre IS NOT NEW.nombre OR OLD.descripcion IS NOT NEW.descripcion OR OLD.exige_evidencia IS NOT NEW.exige_evidencia
                OR (OLD.estado = 'Completada' AND NEW.estado <> 'Completada')
            BEGIN SELECT RAISE(ABORT, 'TAREA_INMUTABLE'); END");
        DB::unprepared("CREATE TRIGGER offboarding_tareas_sin_eliminar BEFORE DELETE ON offboarding_tareas
            BEGIN SELECT RAISE(ABORT, 'TAREA_NO_ELIMINABLE'); END");

        DB::unprepared("CREATE TRIGGER offboarding_entrevistas_terminales BEFORE UPDATE ON offboarding_entrevistas
            WHEN OLD.estado IN ('Completada', 'NoRealizada')
            BEGIN SELECT RAISE(ABORT, 'ENTREVISTA_CERRADA'); END");
        DB::unprepared("CREATE TRIGGER offboarding_entrevistas_sin_eliminar BEFORE DELETE ON offboarding_entrevistas
            BEGIN SELECT RAISE(ABORT, 'ENTREVISTA_NO_ELIMINABLE'); END");

        DB::unprepared("CREATE TRIGGER offboarding_respuestas_sin_insertar_si_cerrada BEFORE INSERT ON offboarding_respuestas
            WHEN (SELECT estado FROM offboarding_entrevistas WHERE id = NEW.entrevista_id) IN ('Completada', 'NoRealizada')
            BEGIN SELECT RAISE(ABORT, 'ENTREVISTA_CERRADA'); END");
        DB::unprepared("CREATE TRIGGER offboarding_respuestas_sin_actualizar_si_cerrada BEFORE UPDATE ON offboarding_respuestas
            WHEN (SELECT estado FROM offboarding_entrevistas WHERE id = OLD.entrevista_id) IN ('Completada', 'NoRealizada')
            BEGIN SELECT RAISE(ABORT, 'ENTREVISTA_CERRADA'); END");
        DB::unprepared("CREATE TRIGGER offboarding_respuestas_sin_eliminar_si_cerrada BEFORE DELETE ON offboarding_respuestas
            WHEN (SELECT estado FROM offboarding_entrevistas WHERE id = OLD.entrevista_id) IN ('Completada', 'NoRealizada')
            BEGIN SELECT RAISE(ABORT, 'ENTREVISTA_CERRADA'); END");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            foreach (['offboarding_tareas_selladas', 'offboarding_tareas_sin_eliminar', 'offboarding_entrevistas_terminales',
                'offboarding_entrevistas_sin_eliminar', 'offboarding_respuestas_sin_insertar_si_cerrada',
                'offboarding_respuestas_sin_actualizar_si_cerrada', 'offboarding_respuestas_sin_eliminar_si_cerrada'] as $trigger) {
                DB::unprepared("DROP TRIGGER IF EXISTS $trigger");
            }
        }
        foreach (['offboarding_respuestas', 'offboarding_entrevistas', 'offboarding_documentos', 'offboarding_tareas'] as $tabla) {
            DB::statement("DROP TABLE IF EXISTS $tabla");
        }
    }
};
