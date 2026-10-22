<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Columnas selladas al publicar: no cambian nunca (solo `retirada_*` y `updated_*` admiten cambios). */
    private const SELLADAS = [
        'id', 'tenant_id', 'politica_id', 'numero', 'descripcion', 'parametros', 'base_version_id', 'version',
        'created_by', 'created_at', 'descartada_en', 'descartada_por', 'publicada_en', 'publicada_por',
        'inicio_vigencia', 'motivo_cambio', 'revisor_nombre', 'revision_observaciones', 'revision_en',
        'condiciones', 'condiciones_hash',
    ];

    public function up(): void
    {
        Schema::create('politica_versiones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('politica_id')->constrained('politicas_personal')->restrictOnDelete();
            $table->unsignedInteger('numero')->nullable();
            $table->string('descripcion', 1000);
            $table->text('parametros');
            $table->foreignId('base_version_id')->nullable()->constrained('politica_versiones')->restrictOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by');
            $table->timestamps();
            $table->timestamp('descartada_en')->nullable();
            $table->unsignedBigInteger('descartada_por')->nullable();
            $table->timestamp('publicada_en')->nullable();
            $table->unsignedBigInteger('publicada_por')->nullable();
            $table->timestamp('inicio_vigencia')->nullable();
            $table->string('motivo_cambio', 500)->nullable();
            $table->string('revisor_nombre', 150)->nullable();
            $table->string('revision_observaciones', 1000)->nullable();
            $table->timestamp('revision_en')->nullable();
            $table->text('condiciones')->nullable();
            $table->char('condiciones_hash', 64)->nullable();
            $table->timestamp('retirada_en')->nullable();
            $table->unsignedBigInteger('retirada_por')->nullable();
            $table->string('retiro_motivo', 500)->nullable();

            $table->index(['politica_id', 'inicio_vigencia', 'numero'], 'politica_versiones_vigencia');
            $table->index(['tenant_id', 'politica_id', 'publicada_en'], 'politica_versiones_historial');
        });

        // Un borrador activo por tipo y numeración única por política (índices parciales).
        DB::statement('CREATE UNIQUE INDEX politica_versiones_un_borrador ON politica_versiones (politica_id) WHERE publicada_en IS NULL AND descartada_en IS NULL');
        DB::statement('CREATE UNIQUE INDEX politica_versiones_numero_unico ON politica_versiones (politica_id, numero) WHERE numero IS NOT NULL');

        if (DB::getDriverName() === 'sqlite') {
            $coherencia = '(NEW.numero IS NULL) <> (NEW.publicada_en IS NULL)
                OR (NEW.inicio_vigencia IS NULL) <> (NEW.publicada_en IS NULL)
                OR (NEW.motivo_cambio IS NULL) <> (NEW.publicada_en IS NULL)
                OR (NEW.revisor_nombre IS NULL) <> (NEW.publicada_en IS NULL)
                OR (NEW.condiciones IS NULL) <> (NEW.publicada_en IS NULL)
                OR (NEW.condiciones_hash IS NULL) <> (NEW.publicada_en IS NULL)
                OR (NEW.retirada_en IS NOT NULL AND NEW.publicada_en IS NULL)
                OR (NEW.retirada_en IS NULL) <> (NEW.retirada_por IS NULL)
                OR (NEW.retirada_en IS NULL) <> (NEW.retiro_motivo IS NULL)
                OR (NEW.descartada_en IS NOT NULL AND NEW.publicada_en IS NOT NULL)';

            foreach (['INSERT', 'UPDATE'] as $evento) {
                DB::unprepared('CREATE TRIGGER politica_versiones_coherencia_'.strtolower($evento)." BEFORE $evento ON politica_versiones
                    BEGIN
                        SELECT RAISE(ABORT, 'version de politica incoherente') WHERE $coherencia;
                    END");
            }

            $cambios = implode(' OR ', array_map(fn (string $c) => "NEW.$c IS NOT OLD.$c", self::SELLADAS));
            DB::unprepared("CREATE TRIGGER politica_versiones_inmutable BEFORE UPDATE ON politica_versiones
                WHEN OLD.publicada_en IS NOT NULL
                BEGIN
                    SELECT RAISE(ABORT, 'version publicada inmutable') WHERE $cambios;
                END");
            DB::unprepared("CREATE TRIGGER politica_versiones_descartada_inmutable BEFORE UPDATE ON politica_versiones
                WHEN OLD.descartada_en IS NOT NULL
                BEGIN
                    SELECT RAISE(ABORT, 'borrador descartado inmutable');
                END");
            DB::unprepared("CREATE TRIGGER politica_versiones_sin_eliminar BEFORE DELETE ON politica_versiones
                WHEN OLD.publicada_en IS NOT NULL
                BEGIN
                    SELECT RAISE(ABORT, 'version publicada no se elimina');
                END");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            foreach (['coherencia_insert', 'coherencia_update', 'inmutable', 'descartada_inmutable', 'sin_eliminar'] as $trigger) {
                DB::unprepared("DROP TRIGGER IF EXISTS politica_versiones_$trigger");
            }
        }
        DB::statement('DROP INDEX IF EXISTS politica_versiones_un_borrador');
        DB::statement('DROP INDEX IF EXISTS politica_versiones_numero_unico');
        Schema::dropIfExists('politica_versiones');
    }
};
