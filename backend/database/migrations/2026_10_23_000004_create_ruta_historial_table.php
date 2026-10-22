<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ruta_historial', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('ruta_id')->constrained('rutas')->restrictOnDelete();
            $table->unsignedBigInteger('inscripcion_id')->nullable(); // solo eventos de inscripción
            $table->string('tipo', 12); // Ruta | Nivel | Inscripcion
            $table->string('accion', 30);
            $table->json('valor_anterior')->nullable();
            $table->json('valor_nuevo')->nullable();
            $table->string('motivo', 500)->nullable();
            $table->unsignedBigInteger('user_id')->nullable(); // nulo = sistema
            $table->timestamp('created_at')->nullable(); // solo se inserta: no se actualiza ni se borra

            $table->index(['ruta_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ruta_historial');
    }
};
