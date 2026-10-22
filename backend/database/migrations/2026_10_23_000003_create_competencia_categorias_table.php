<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nombres propios de la escala Categorías: cuatro filas o ninguna por competencia.
        Schema::create('competencia_categorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competencia_id')->constrained('competencias')->restrictOnDelete();
            $table->unsignedTinyInteger('orden'); // 1 a 4
            $table->string('nombre', 20);
            // Nombre recortado, en minúsculas y sin tildes: no se repite dentro de la competencia.
            $table->string('nombre_clave', 20);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['competencia_id', 'orden']);
            $table->unique(['competencia_id', 'nombre_clave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competencia_categorias');
    }
};
