<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encuestas_satisfaccion', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('actividad_id'); // referencia lógica a actividades (sin FK)
            $table->unsignedBigInteger('colaborador_id'); // referencia lógica a colaboradores (sin FK)
            $table->unsignedTinyInteger('calificacion'); // 1 a 5
            $table->text('comentario')->nullable(); // máximo 1 000 caracteres (validación)
            $table->timestamp('created_at')->nullable(); // inmutable: no hay updated_at

            $table->unique(['actividad_id', 'colaborador_id']);
            $table->index(['tenant_id', 'actividad_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encuestas_satisfaccion');
    }
};
