<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_acciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('planes')->restrictOnDelete();
            $table->foreignId('curso_id')->nullable()->constrained('cursos')->restrictOnDelete();
            $table->string('nombre', 120);
            $table->string('tipo', 12)->nullable(); // Presencial | Virtual | ELearning | OJT | Codesarrollo | Mentoring
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->decimal('duracion_horas', 6, 2)->nullable(); // > 0
            $table->string('formador', 120)->nullable();
            $table->unsignedInteger('capacidad_maxima')->nullable(); // > 0
            $table->decimal('costo', 12, 2)->nullable(); // >= 0, USD
            $table->boolean('cancelada')->default(false);
            $table->string('motivo_cancelacion', 500)->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by');
            $table->timestamps();

            $table->index('plan_id');
            $table->index(['fecha_inicio', 'fecha_fin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_acciones');
    }
};
