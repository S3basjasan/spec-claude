<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adecuacion_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('adecuacion_id')->constrained('adecuaciones')->restrictOnDelete();
            $table->string('dimension', 12); // Competencia | Conocimiento | Experiencia
            $table->string('tipo_elemento', 12)->nullable(); // Competencia | Conocimiento; nulo en Experiencia
            $table->unsignedBigInteger('elemento_id')->nullable(); // referencia lógica a Estructura; nulo en Experiencia
            $table->string('nombre_elemento', 150)->nullable(); // copia del momento
            $table->char('requerido_actual_nivel', 1)->nullable();
            $table->char('requerido_objetivo_nivel', 1)->nullable();
            $table->char('valor_nivel', 1)->nullable();
            $table->decimal('requerido_actual_anios', 4, 1)->nullable();
            $table->decimal('requerido_objetivo_anios', 4, 1)->nullable();
            $table->decimal('valor_anios', 4, 1)->nullable();
            $table->decimal('diferencia', 5, 1)->nullable();
            $table->string('estado', 12); // ConBrecha | Cumple | Supera | SinEvaluacion
            $table->unsignedSmallInteger('tiempo_cierre_meses')->nullable(); // obligatorio (>= 1) si estado = ConBrecha
            $table->timestamp('created_at')->nullable();

            $table->unique(['adecuacion_id', 'dimension', 'tipo_elemento', 'elemento_id'], 'adecuacion_detalles_requisito_unico');
            $table->index('adecuacion_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adecuacion_detalles');
    }
};
