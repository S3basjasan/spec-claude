<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adecuaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('codigo', 12); // ADE-0001, generado por el sistema
            $table->unsignedBigInteger('colaborador_id'); // referencia lógica a Gestión de Personal
            $table->unsignedBigInteger('puesto_actual_id')->nullable(); // copia del momento; nulo si no se pudo resolver
            $table->unsignedBigInteger('puesto_objetivo_id'); // referencia lógica a Estructura
            $table->foreignId('inscripcion_id')->nullable()->constrained('ruta_inscripciones')->restrictOnDelete();
            $table->foreignId('nivel_objetivo_id')->nullable()->constrained('ruta_niveles')->restrictOnDelete();
            $table->decimal('experiencia_requerida_anios', 4, 1)->nullable();
            $table->string('preparacion', 12); // Lista | EnDesarrollo | NoLista | Incompleta
            $table->unsignedSmallInteger('umbral_meses'); // copia del parámetro vigente
            $table->string('observaciones', 500)->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps(); // updated_at nunca se modifica: la adecuación es inmutable

            $table->unique(['tenant_id', 'codigo']);
            $table->index(['tenant_id', 'colaborador_id', 'puesto_objetivo_id', 'created_at'], 'adecuaciones_vigente_idx');
            $table->index(['tenant_id', 'preparacion']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adecuaciones');
    }
};
