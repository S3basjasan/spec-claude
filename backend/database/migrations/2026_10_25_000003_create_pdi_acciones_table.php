<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdi_acciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('compromiso_id')->constrained('pdi_compromisos')->restrictOnDelete();
            $table->unsignedSmallInteger('orden');
            $table->string('tipo', 20); // Curso | Mentoring | Coaching | Autodesarrollo | Codesarrollo | NuevaResponsabilidad | Otra
            $table->string('descripcion', 500);
            $table->string('persona_cargo', 150)->nullable();
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->unsignedBigInteger('plan_accion_id')->nullable(); // referencia lógica a una acción del Plan de Formación; no única
            $table->string('estado', 10)->default('Pendiente'); // Pendiente | EnCurso | Cumplida | Cancelada
            $table->boolean('estado_congelado')->default(false); // verdadero tras cerrar el plan en acciones vinculadas
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by');
            $table->timestamps();

            $table->index(['compromiso_id', 'orden']);
            $table->index(['tenant_id', 'plan_accion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdi_acciones');
    }
};
