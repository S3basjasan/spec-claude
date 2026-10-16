<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos_colaborador', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('colaborador_id')->constrained('colaboradores')->restrictOnDelete();
            $table->foreignId('categoria_id')->constrained('categorias_documentales')->restrictOnDelete();
            $table->string('titulo', 200);
            $table->string('titulo_busqueda', 200);
            $table->string('object_key', 512)->unique();
            $table->string('mime_type', 64);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->boolean('visible_colaborador')->default(false);
            $table->string('estado', 10)->default('Disponible');
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by');
            $table->unsignedBigInteger('archived_by')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->char('operacion_id', 36);
            $table->char('operacion_hash', 64);
            $table->timestamps();

            $table->unique(['tenant_id', 'operacion_id'], 'documentos_colaborador_operacion_unica');
            $table->index(['tenant_id', 'colaborador_id', 'estado', 'created_at', 'id'], 'documentos_colaborador_listado');
        });

        if (DB::getDriverName() === 'sqlite') {
            foreach (['INSERT', 'UPDATE'] as $evento) {
                DB::unprepared('CREATE TRIGGER documentos_colaborador_validar_'.strtolower($evento)." BEFORE $evento ON documentos_colaborador
                    BEGIN
                        SELECT RAISE(ABORT, 'estado de documento invalido')
                        WHERE NEW.estado NOT IN ('Disponible','Archivado');
                        SELECT RAISE(ABORT, 'tamano de documento invalido')
                        WHERE NEW.size_bytes < 1 OR NEW.size_bytes > 10000000;
                        SELECT RAISE(ABORT, 'archivo incompleto')
                        WHERE NEW.estado = 'Archivado' AND (NEW.archived_by IS NULL OR NEW.archived_at IS NULL);
                        SELECT RAISE(ABORT, 'documento disponible con datos de archivo')
                        WHERE NEW.estado = 'Disponible' AND (NEW.archived_by IS NOT NULL OR NEW.archived_at IS NOT NULL);
                    END");
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS documentos_colaborador_validar_insert');
            DB::unprepared('DROP TRIGGER IF EXISTS documentos_colaborador_validar_update');
        }
        Schema::dropIfExists('documentos_colaborador');
    }
};
