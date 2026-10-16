<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Plan de formación anual (feature 013). Los planes nunca se eliminan: el estado es un campo de negocio.
        Schema::create('planes', function (Blueprint $table) {
            $table->id();
            // Referencias lógicas a otros módulos: sin clave foránea.
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedInteger('numero');
            $table->string('codigo', 12); // PLAN-0001
            $table->string('nombre', 120);
            $table->string('nombre_normalizado', 120);
            $table->unsignedSmallInteger('anio');
            $table->text('descripcion')->nullable();
            $table->string('estado', 12)->default('Borrador'); // Borrador | Revision | Aprobado | Ejecutando | Cerrado
            $table->unsignedBigInteger('aprobador_id')->nullable();
            $table->unsignedBigInteger('origen_dnf_id')->nullable();
            $table->timestamp('fecha_aprobacion')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by');
            $table->timestamps();

            $table->unique(['tenant_id', 'numero']);
            $table->unique(['tenant_id', 'anio', 'nombre_normalizado']);
            $table->index(['tenant_id', 'anio', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planes');
    }
};
