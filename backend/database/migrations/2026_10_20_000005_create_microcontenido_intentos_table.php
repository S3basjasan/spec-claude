<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('microcontenido_intentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('progreso_id')->constrained('microcontenido_progresos')->restrictOnDelete();
            $table->string('estado', 10); // EnCurso | Finalizado
            $table->unsignedInteger('puntaje')->nullable(); // respuestas correctas; nulo mientras EnCurso
            $table->unsignedInteger('total_preguntas'); // ≥ 1
            $table->dateTime('finalizado_en')->nullable();
            $table->timestamps();

            $table->index(['progreso_id', 'estado'], 'micro_intento_progreso_estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('microcontenido_intentos');
    }
};
