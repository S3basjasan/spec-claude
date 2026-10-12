<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Una fila por pregunta: cada registro tiene exactamente cuatro, numeradas del 1 al 4.
        Schema::create('competencia_preguntas', function (Blueprint $table) {
            $table->id();
            // Sin borrado en cascada: los registros nunca se eliminan.
            $table->foreignId('registro_id')->constrained('competencia_pregunta_registros')->restrictOnDelete();
            $table->unsignedTinyInteger('orden');
            $table->string('pregunta', 500);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            // Máximo cuatro preguntas por registro y sin números repetidos.
            $table->unique(['registro_id', 'orden']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('competencia_preguntas');
    }
};
