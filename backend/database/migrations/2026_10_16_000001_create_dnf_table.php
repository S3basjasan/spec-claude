<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Diagnóstico de necesidades formativas (feature 006). Inmutable: solo se completa una vez el plan generado.
        Schema::create('dnf', function (Blueprint $table) {
            $table->id();
            // Referencias lógicas a otros módulos: sin clave foránea.
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('codigo', 12);
            $table->string('alcance', 12); // Individual | Departamento
            $table->unsignedBigInteger('departamento_id')->nullable();
            $table->unsignedInteger('competencias_omitidas')->default(0);
            $table->unsignedBigInteger('plan_formacion_id')->nullable()->unique();
            $table->unsignedBigInteger('plan_generado_por')->nullable();
            $table->timestamp('plan_generado_en')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->unique(['tenant_id', 'codigo']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dnf');
    }
};
