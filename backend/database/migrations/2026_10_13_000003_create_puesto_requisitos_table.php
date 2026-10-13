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
        // Sin columna de orden: se devuelven por tipo (Formacion, Experiencia, Capacitacion) y luego por id.
        Schema::create('puesto_requisitos', function (Blueprint $table) {
            $table->id();
            // Los puestos nunca se eliminan (sin cascada).
            $table->foreignId('puesto_id')->constrained('puestos')->restrictOnDelete();
            $table->string('tipo', 15); // Formacion | Experiencia | Capacitacion (sin tildes)
            $table->string('descripcion', 500);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('puesto_requisitos');
    }
};
