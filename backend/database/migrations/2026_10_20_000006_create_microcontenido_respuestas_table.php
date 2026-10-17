<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('microcontenido_respuestas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intento_id')->constrained('microcontenido_intentos')->restrictOnDelete();
            $table->foreignId('pregunta_id')->constrained('microcontenido_preguntas')->restrictOnDelete();
            $table->foreignId('opcion_id')->constrained('microcontenido_opciones')->restrictOnDelete();
            $table->boolean('es_correcta'); // resultado en el momento de responder
            $table->timestamp('created_at')->nullable();

            $table->unique(['intento_id', 'pregunta_id'], 'micro_respuesta_unica');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('microcontenido_respuestas');
    }
};
