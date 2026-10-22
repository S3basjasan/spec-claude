<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actividades', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index(); // tomado del plan de la acción, sin FK
            $table->unsignedInteger('numero');
            $table->string('codigo', 12); // ACT-0001
            $table->foreignId('plan_accion_id')->constrained('plan_acciones')->restrictOnDelete();
            $table->string('lugar_enlace', 500);
            // Referencia lógica a una unidad de Estructura Empresarial: sin clave foránea.
            $table->unsignedBigInteger('departamento_id')->nullable()->index();
            $table->unsignedInteger('version')->default(1); // control optimista del registro de asistencia
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by');
            $table->timestamps();

            $table->unique('plan_accion_id'); // una acción tiene como máximo una actividad
            $table->unique(['tenant_id', 'numero']);
            $table->unique(['tenant_id', 'codigo']);
            $table->index(['tenant_id', 'departamento_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actividades');
    }
};
