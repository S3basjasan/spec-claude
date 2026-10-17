<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('microcontenido_preguntas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('microcontenido_id')->constrained('microcontenidos')->restrictOnDelete();
            $table->string('enunciado', 500);
            $table->unsignedInteger('orden'); // ≥ 1
            $table->timestamps();

            $table->unique(['microcontenido_id', 'orden'], 'micro_pregunta_orden_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('microcontenido_preguntas');
    }
};
