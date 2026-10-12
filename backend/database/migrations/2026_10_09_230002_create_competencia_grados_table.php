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
        Schema::create('competencia_grados', function (Blueprint $table) {
            $table->id();
            // Las competencias nunca se eliminan: sin borrado en cascada.
            $table->foreignId('competencia_id')->constrained('competencias')->restrictOnDelete();
            $table->string('grado', 1);
            $table->string('descripcion', 500);
            $table->unique(['competencia_id', 'grado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('competencia_grados');
    }
};
