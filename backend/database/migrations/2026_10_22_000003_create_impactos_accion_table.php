<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impactos_accion', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('plan_accion_id')->unique(); // referencia lógica a plan_acciones (sin FK)
            $table->string('indicador', 150);
            $table->string('unidad', 50);
            $table->decimal('valor_antes', 14, 4);
            $table->decimal('valor_despues', 14, 4);
            $table->decimal('beneficio_estimado', 12, 2); // USD, mayor o igual a 0 (validado en la aplicación)
            $table->text('justificacion'); // máximo 2 000 caracteres (validación)
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impactos_accion');
    }
};
