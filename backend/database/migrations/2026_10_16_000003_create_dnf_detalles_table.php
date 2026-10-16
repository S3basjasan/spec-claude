<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dnf_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dnf_colaborador_id')->constrained('dnf_colaboradores')->restrictOnDelete();
            $table->unsignedBigInteger('competencia_id'); // referencia lógica a Estructura, sin clave foránea
            $table->string('tipo_competencia', 12); // Cardinal | Especifica (copia del momento)
            $table->char('nivel_requerido', 1); // A-D
            $table->char('nivel_actual', 1)->nullable(); // nulo => SinEvaluacion
            $table->smallInteger('diferencia')->nullable(); // actual - requerido, en grados
            $table->decimal('brecha_porcentaje', 5, 2)->nullable();
            $table->string('prioridad', 7)->nullable(); // Critica | Alta | Media | Baja (solo ConBrecha)
            $table->string('estado', 14); // ConBrecha | Cumple | Supera | SinEvaluacion

            $table->unique(['dnf_colaborador_id', 'competencia_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dnf_detalles');
    }
};
