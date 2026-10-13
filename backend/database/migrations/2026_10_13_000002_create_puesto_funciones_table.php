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
        Schema::create('puesto_funciones', function (Blueprint $table) {
            $table->id();
            // Los puestos nunca se eliminan (sin cascada).
            $table->foreignId('puesto_id')->constrained('puestos')->restrictOnDelete();
            $table->string('descripcion', 500);
            $table->string('tipo', 15); // Esencial | Complementaria
            $table->unsignedInteger('orden'); // 1 a n, definido por el usuario

            // Sin números de orden repetidos dentro de un puesto.
            $table->unique(['puesto_id', 'orden']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('puesto_funciones');
    }
};
