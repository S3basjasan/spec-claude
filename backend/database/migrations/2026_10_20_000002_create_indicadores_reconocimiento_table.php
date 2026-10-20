<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indicadores_reconocimiento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categoria_reconocimiento_id')->constrained('categorias_reconocimiento')->restrictOnDelete();
            $table->unsignedSmallInteger('orden'); // 1 a 5, consecutivo
            $table->string('texto', 250);
            $table->string('texto_normalizado', 250);
            $table->timestamps();

            $table->unique(['categoria_reconocimiento_id', 'orden'], 'indicadores_reconocimiento_orden_unico');
            $table->unique(['categoria_reconocimiento_id', 'texto_normalizado'], 'indicadores_reconocimiento_texto_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicadores_reconocimiento');
    }
};
