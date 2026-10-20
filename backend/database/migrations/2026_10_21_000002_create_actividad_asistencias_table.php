<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actividad_asistencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actividad_id')->constrained('actividades')->restrictOnDelete();
            // Referencia lógica a Gestión de Personal: sin clave foránea.
            $table->unsignedBigInteger('colaborador_id')->index();
            $table->string('asistencia', 12); // Presente | Ausente | Justificado (Pendiente = sin fila)
            $table->unsignedTinyInteger('calificacion')->nullable(); // 1..5, solo con Presente
            $table->text('observaciones')->nullable(); // interno, máx. 1 000 caracteres, solo con asistencia registrada
            $table->unsignedBigInteger('registrado_by');
            $table->timestamps();

            $table->unique(['actividad_id', 'colaborador_id']);
        });

        // Refuerzo de la regla de la aplicación (SQLite y los motores comunes admiten CHECK al crear la tabla).
        if (DB::getDriverName() === 'sqlite') {
            return;
        }
        DB::statement("ALTER TABLE actividad_asistencias ADD CONSTRAINT actividad_asistencias_calificacion_chk CHECK (calificacion IS NULL OR (calificacion BETWEEN 1 AND 5 AND asistencia = 'Presente'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('actividad_asistencias');
    }
};
