<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_accion_participantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_accion_id')->constrained('plan_acciones')->restrictOnDelete();
            // Referencia lógica a Gestión de Personal: sin clave foránea.
            $table->unsignedBigInteger('colaborador_id')->index();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->unique(['plan_accion_id', 'colaborador_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_accion_participantes');
    }
};
