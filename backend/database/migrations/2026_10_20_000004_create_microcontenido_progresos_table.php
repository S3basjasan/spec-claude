<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('microcontenido_progresos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('microcontenido_id')->constrained('microcontenidos')->restrictOnDelete();
            $table->unsignedBigInteger('colaborador_id'); // referencia lógica a Gestión de Personal, sin FK
            $table->unsignedBigInteger('tenant_id')->index(); // copiado de la cuenta del colaborador, sin FK
            $table->string('estado', 11); // EnProgreso | Completado
            $table->unsignedInteger('posicion_segundos')->nullable(); // solo Video, ≥ 0
            $table->dateTime('fecha_inicio');
            $table->dateTime('fecha_completado')->nullable();
            $table->timestamps();

            $table->unique(['microcontenido_id', 'colaborador_id'], 'micro_progreso_unico');
            $table->index(['tenant_id', 'estado'], 'micro_progreso_tenant_estado');
            $table->index(['colaborador_id', 'estado'], 'micro_progreso_colaborador_estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('microcontenido_progresos');
    }
};
