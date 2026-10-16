<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formaciones_academicas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('colaborador_id')->constrained('colaboradores')->restrictOnDelete();
            $table->string('nivel', 100);
            $table->string('titulo_programa', 200);
            $table->string('institucion', 200);
            $table->string('estado', 12);
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_finalizacion')->nullable();
            $table->string('observaciones', 2000)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by');
            $table->timestamps();

            $table->index(['tenant_id', 'colaborador_id', 'fecha_inicio', 'id'], 'formaciones_orden');
        });

        if (DB::getDriverName() === 'sqlite') {
            foreach (['INSERT', 'UPDATE'] as $evento) {
                DB::unprepared('CREATE TRIGGER formaciones_validar_'.strtolower($evento)." BEFORE $evento ON formaciones_academicas
                    BEGIN
                        SELECT RAISE(ABORT, 'estado de formacion invalido')
                        WHERE NEW.estado NOT IN ('EnCurso','Completada','Incompleta');
                        SELECT RAISE(ABORT, 'fechas de formacion invalidas')
                        WHERE NEW.fecha_finalizacion IS NOT NULL AND NEW.fecha_inicio IS NOT NULL AND NEW.fecha_finalizacion < NEW.fecha_inicio;
                    END");
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS formaciones_validar_insert');
            DB::unprepared('DROP TRIGGER IF EXISTS formaciones_validar_update');
        }
        Schema::dropIfExists('formaciones_academicas');
    }
};
