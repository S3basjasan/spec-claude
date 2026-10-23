<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdi_compromisos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('pdi_id')->constrained('pdis')->restrictOnDelete();
            $table->unsignedSmallInteger('orden');
            $table->string('dimension', 12); // Competencia | Conocimiento | Experiencia | Meta
            $table->string('tipo_elemento', 12)->nullable(); // Competencia | Conocimiento; solo en esas dimensiones
            $table->unsignedBigInteger('elemento_id')->nullable(); // referencia lógica a Estructura
            $table->string('descripcion', 500)->nullable(); // obligatoria en Experiencia y Meta
            $table->char('nivel_actual', 1)->nullable(); // A–D
            $table->char('nivel_objetivo', 1)->nullable(); // A–D
            $table->string('indicador', 500)->nullable();
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->string('resultado', 10)->nullable(); // Cerrada | Parcial | NoCerrada; se guarda al cerrar
            $table->timestamps();

            $table->index(['pdi_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdi_compromisos');
    }
};
