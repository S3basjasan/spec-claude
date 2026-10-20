<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Historial propio del módulo, de solo inserción: sin updated_at ni borrado en cascada.
        Schema::create('auditoria_categorias_reconocimiento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categoria_reconocimiento_id')->constrained('categorias_reconocimiento')->restrictOnDelete();
            $table->string('campo', 30); // nombre, descripcion, competencia_id, puntuacion_base, icono, indicadores, estado
            $table->text('valor_anterior')->nullable();
            $table->text('valor_nuevo')->nullable();
            $table->unsignedBigInteger('user_id')->nullable(); // sin clave foránea
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria_categorias_reconocimiento');
    }
};
