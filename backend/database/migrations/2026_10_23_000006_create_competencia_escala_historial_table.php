<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Historial de solo inserción: sin updated_at y sin borrado en cascada.
        Schema::create('competencia_escala_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competencia_id')->constrained('competencias')->restrictOnDelete();
            $table->string('escala_anterior', 30);
            $table->string('escala_nueva', 30);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competencia_escala_historial');
    }
};
