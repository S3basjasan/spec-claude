<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('puesto_competencias', function (Blueprint $table) {
            $table->id();
            // Ni los puestos ni las competencias se eliminan (sin cascada).
            $table->foreignId('puesto_id')->constrained('puestos')->restrictOnDelete();
            $table->foreignId('competencia_id')->constrained('competencias')->restrictOnDelete();
            // Que el grado exista para la competencia (competencia_grados) se valida en la aplicación.
            $table->string('grado_requerido', 1); // A | B | C | D

            // Una competencia aparece como máximo una vez por puesto.
            $table->unique(['puesto_id', 'competencia_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('puesto_competencias');
    }
};
