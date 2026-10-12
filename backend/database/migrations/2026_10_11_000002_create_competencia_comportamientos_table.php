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
        Schema::create('competencia_comportamientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competencia_grado_id')->constrained('competencia_grados')->restrictOnDelete();
            $table->unsignedTinyInteger('orden'); // 1 a 4
            $table->string('descripcion', 300);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            // Máximo 4 comportamientos por grado y sin posiciones repetidas.
            $table->unique(['competencia_grado_id', 'orden']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('competencia_comportamientos');
    }
};
