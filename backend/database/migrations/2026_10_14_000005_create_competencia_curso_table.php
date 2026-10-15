<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competencia_curso', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('competencia_id')->index();
            $table->foreignId('curso_id')->constrained('cursos')->restrictOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['competencia_id', 'curso_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competencia_curso');
    }
};
