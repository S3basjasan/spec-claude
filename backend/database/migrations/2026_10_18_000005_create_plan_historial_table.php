<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Solo se agrega: nunca se actualiza ni se elimina.
        Schema::create('plan_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('planes')->restrictOnDelete();
            $table->foreignId('plan_accion_id')->nullable()->constrained('plan_acciones')->restrictOnDelete();
            $table->string('tipo', 12); // Estado | Participante | Cancelacion | Aprobador
            $table->string('valor_anterior', 120)->nullable();
            $table->string('valor_nuevo', 120)->nullable();
            $table->string('motivo', 500)->nullable();
            $table->unsignedBigInteger('user_id');
            $table->timestamp('created_at')->nullable();

            $table->index(['plan_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_historial');
    }
};
