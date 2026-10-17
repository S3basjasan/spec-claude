<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('microcontenido_opciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pregunta_id')->constrained('microcontenido_preguntas')->restrictOnDelete();
            $table->string('texto', 300);
            $table->boolean('es_correcta'); // exactamente una por pregunta, validado en la aplicación
            $table->unsignedInteger('orden'); // ≥ 1
            $table->timestamps();

            $table->unique(['pregunta_id', 'orden'], 'micro_opcion_orden_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('microcontenido_opciones');
    }
};
