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
        // Cadena lineal de tipos de unidad: `nivel` 1 es el tipo raíz y los niveles son continuos 1..N (la continuidad
        // y la unicidad del nombre sin tildes ni mayúsculas las mantiene la aplicación). Los tipos nunca se eliminan.
        Schema::create('tipos_unidad', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 50)->unique();
            $table->tinyInteger('nivel')->unique();
            $table->unsignedBigInteger('created_by')->nullable(); // sin FK a users
            $table->unsignedBigInteger('updated_by')->nullable(); // sin FK a users
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipos_unidad');
    }
};
