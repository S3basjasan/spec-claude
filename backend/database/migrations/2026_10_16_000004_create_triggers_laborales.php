<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Respaldo en la base de las garantías temporales (equivalente a las restricciones de exclusión del insumo).
     * Intervalo [inicio, fin) con fin NULL = abierto; un intervalo vacío (inicio = fin) no ocupa.
     */
    public function up(): void
    {
        $inf = "'9999-12-31'";

        DB::statement(<<<SQL
            CREATE TRIGGER vinculos_sin_superposicion_ins BEFORE INSERT ON vinculos_laborales
            WHEN NEW.fecha_ingreso < COALESCE(NEW.fecha_salida, $inf)
            BEGIN
                SELECT RAISE(ABORT, 'VINCULO_SUPERPUESTO')
                WHERE EXISTS (
                    SELECT 1 FROM vinculos_laborales v
                    WHERE v.colaborador_id = NEW.colaborador_id
                      AND v.fecha_ingreso < COALESCE(v.fecha_salida, $inf)
                      AND v.fecha_ingreso < COALESCE(NEW.fecha_salida, $inf)
                      AND NEW.fecha_ingreso < COALESCE(v.fecha_salida, $inf)
                );
            END
        SQL);

        DB::statement(<<<SQL
            CREATE TRIGGER vinculos_sin_superposicion_upd BEFORE UPDATE OF fecha_ingreso, fecha_salida ON vinculos_laborales
            WHEN NEW.fecha_ingreso < COALESCE(NEW.fecha_salida, $inf)
            BEGIN
                SELECT RAISE(ABORT, 'VINCULO_SUPERPUESTO')
                WHERE EXISTS (
                    SELECT 1 FROM vinculos_laborales v
                    WHERE v.colaborador_id = NEW.colaborador_id AND v.id <> NEW.id
                      AND v.fecha_ingreso < COALESCE(v.fecha_salida, $inf)
                      AND v.fecha_ingreso < COALESCE(NEW.fecha_salida, $inf)
                      AND NEW.fecha_ingreso < COALESCE(v.fecha_salida, $inf)
                );
            END
        SQL);

        DB::statement(<<<SQL
            CREATE TRIGGER posicion_sin_superposicion_ins BEFORE INSERT ON asignaciones_laborales
            WHEN NEW.fecha_inicio < COALESCE(NEW.fecha_fin, $inf)
            BEGIN
                SELECT RAISE(ABORT, 'POSICION_SUPERPUESTA')
                WHERE EXISTS (
                    SELECT 1 FROM asignaciones_laborales a
                    WHERE a.posicion_id = NEW.posicion_id
                      AND a.fecha_inicio < COALESCE(a.fecha_fin, $inf)
                      AND a.fecha_inicio < COALESCE(NEW.fecha_fin, $inf)
                      AND NEW.fecha_inicio < COALESCE(a.fecha_fin, $inf)
                );
                SELECT RAISE(ABORT, 'ASIGNACION_SUPERPUESTA')
                WHERE EXISTS (
                    SELECT 1 FROM asignaciones_laborales a
                    WHERE a.vinculo_id = NEW.vinculo_id
                      AND a.fecha_inicio < COALESCE(a.fecha_fin, $inf)
                      AND a.fecha_inicio < COALESCE(NEW.fecha_fin, $inf)
                      AND NEW.fecha_inicio < COALESCE(a.fecha_fin, $inf)
                );
            END
        SQL);

        DB::statement(<<<SQL
            CREATE TRIGGER posicion_sin_superposicion_upd BEFORE UPDATE OF posicion_id, fecha_inicio, fecha_fin ON asignaciones_laborales
            WHEN NEW.fecha_inicio < COALESCE(NEW.fecha_fin, $inf)
            BEGIN
                SELECT RAISE(ABORT, 'POSICION_SUPERPUESTA')
                WHERE EXISTS (
                    SELECT 1 FROM asignaciones_laborales a
                    WHERE a.posicion_id = NEW.posicion_id AND a.id <> NEW.id
                      AND a.fecha_inicio < COALESCE(a.fecha_fin, $inf)
                      AND a.fecha_inicio < COALESCE(NEW.fecha_fin, $inf)
                      AND NEW.fecha_inicio < COALESCE(a.fecha_fin, $inf)
                );
                SELECT RAISE(ABORT, 'ASIGNACION_SUPERPUESTA')
                WHERE EXISTS (
                    SELECT 1 FROM asignaciones_laborales a
                    WHERE a.vinculo_id = NEW.vinculo_id AND a.id <> NEW.id
                      AND a.fecha_inicio < COALESCE(a.fecha_fin, $inf)
                      AND a.fecha_inicio < COALESCE(NEW.fecha_fin, $inf)
                      AND NEW.fecha_inicio < COALESCE(a.fecha_fin, $inf)
                );
            END
        SQL);

        // Contención: la asignación debe quedar dentro de su vínculo.
        foreach (['ins' => 'INSERT', 'upd' => 'UPDATE'] as $sufijo => $evento) {
            DB::statement(<<<SQL
                CREATE TRIGGER asignacion_contenida_en_vinculo_$sufijo BEFORE $evento ON asignaciones_laborales
                BEGIN
                    SELECT RAISE(ABORT, 'ASIGNACION_FUERA_DE_VINCULO')
                    WHERE EXISTS (
                        SELECT 1 FROM vinculos_laborales v
                        WHERE v.id = NEW.vinculo_id
                          AND (NEW.fecha_inicio < v.fecha_ingreso
                               OR COALESCE(NEW.fecha_fin, $inf) > COALESCE(v.fecha_salida, $inf))
                    );
                END
            SQL);
        }

        DB::statement(<<<SQL
            CREATE TRIGGER vinculo_contiene_asignaciones BEFORE UPDATE OF fecha_ingreso, fecha_salida ON vinculos_laborales
            BEGIN
                SELECT RAISE(ABORT, 'VINCULO_ACORTADO_SOBRE_ASIGNACION')
                WHERE EXISTS (
                    SELECT 1 FROM asignaciones_laborales a
                    WHERE a.vinculo_id = NEW.id
                      AND (a.fecha_inicio < NEW.fecha_ingreso
                           OR COALESCE(a.fecha_fin, $inf) > COALESCE(NEW.fecha_salida, $inf))
                );
            END
        SQL);
    }

    public function down(): void
    {
        foreach ([
            'vinculos_sin_superposicion_ins', 'vinculos_sin_superposicion_upd',
            'posicion_sin_superposicion_ins', 'posicion_sin_superposicion_upd',
            'asignacion_contenida_en_vinculo_ins', 'asignacion_contenida_en_vinculo_upd',
            'vinculo_contiene_asignaciones',
        ] as $trigger) {
            DB::statement("DROP TRIGGER IF EXISTS $trigger");
        }
    }
};
