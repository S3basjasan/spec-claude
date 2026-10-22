<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Auditoría de administración: solo se agrega, nunca se actualiza ni se elimina.
        Schema::create('politica_acciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('politica_id')->constrained('politicas_personal')->restrictOnDelete();
            $table->foreignId('version_id')->constrained('politica_versiones')->restrictOnDelete();
            $table->string('accion', 20);
            $table->unsignedBigInteger('actor_id');
            $table->string('motivo', 500)->nullable();
            $table->char('operacion_id', 36)->nullable();
            $table->char('operacion_hash', 64)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['politica_id', 'created_at']);
        });

        DB::statement('CREATE UNIQUE INDEX politica_acciones_operacion_unica ON politica_acciones (tenant_id, operacion_id) WHERE operacion_id IS NOT NULL');

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER politica_acciones_validar BEFORE INSERT ON politica_acciones
                BEGIN
                    SELECT RAISE(ABORT, 'accion de politica invalida')
                    WHERE NEW.accion NOT IN ('BorradorCreado','Publicacion','Retiro','BorradorDescartado');
                END");
            DB::unprepared("CREATE TRIGGER politica_acciones_sin_actualizar BEFORE UPDATE ON politica_acciones
                BEGIN
                    SELECT RAISE(ABORT, 'auditoria de politicas es de solo insercion');
                END");
            DB::unprepared("CREATE TRIGGER politica_acciones_sin_eliminar BEFORE DELETE ON politica_acciones
                BEGIN
                    SELECT RAISE(ABORT, 'auditoria de politicas es de solo insercion');
                END");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            foreach (['validar', 'sin_actualizar', 'sin_eliminar'] as $trigger) {
                DB::unprepared("DROP TRIGGER IF EXISTS politica_acciones_$trigger");
            }
        }
        DB::statement('DROP INDEX IF EXISTS politica_acciones_operacion_unica');
        Schema::dropIfExists('politica_acciones');
    }
};
