<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Una fila por competencia configurada. `competencia_id` es una referencia lógica a Estructura (sin FK).
        Schema::create('competencia_formativa', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('competencia_id')->unique();
            $table->foreignId('area_formativa_id')->nullable()->constrained('areas_formativas')->restrictOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competencia_formativa');
    }
};
