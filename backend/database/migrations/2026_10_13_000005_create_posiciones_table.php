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
        // Tabla MÍNIMA reservada para la feature 005 (posiciones): el catálogo de puestos solo la lee
        // para decidir si un puesto puede desactivarse. 005 amplía esta tabla con migraciones propias;
        // no recrearla.
        Schema::create('posiciones', function (Blueprint $table) {
            $table->id();
            // Los puestos nunca se eliminan (sin cascada).
            $table->foreignId('puesto_id')->constrained('puestos')->restrictOnDelete();
            $table->string('estado', 10)->index(); // Ocupada | Vacante | Suprimida
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posiciones');
    }
};
