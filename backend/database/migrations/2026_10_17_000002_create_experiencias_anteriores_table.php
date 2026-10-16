<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('experiencias_anteriores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('colaborador_id')->constrained('colaboradores')->restrictOnDelete();
            $table->string('organizacion', 200);
            $table->string('cargo', 200);
            $table->date('fecha_inicio');
            $table->date('fecha_finalizacion')->nullable();
            $table->string('funciones', 2000)->nullable();
            $table->string('observaciones', 2000)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by');
            $table->timestamps();

            $table->index(['tenant_id', 'colaborador_id', 'fecha_inicio', 'id'], 'experiencias_orden');
        });

        if (DB::getDriverName() === 'sqlite') {
            foreach (['INSERT', 'UPDATE'] as $evento) {
                DB::unprepared('CREATE TRIGGER experiencias_validar_'.strtolower($evento)." BEFORE $evento ON experiencias_anteriores
                    BEGIN
                        SELECT RAISE(ABORT, 'fechas de experiencia invalidas')
                        WHERE NEW.fecha_finalizacion IS NOT NULL AND NEW.fecha_finalizacion < NEW.fecha_inicio;
                    END");
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS experiencias_validar_insert');
            DB::unprepared('DROP TRIGGER IF EXISTS experiencias_validar_update');
        }
        Schema::dropIfExists('experiencias_anteriores');
    }
};
