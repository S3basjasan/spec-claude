<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competencia_microcontenido', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('competencia_id')->index();
            $table->foreignId('microcontenido_id')->constrained('microcontenidos')->restrictOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['competencia_id', 'microcontenido_id'], 'competencia_micro_unica');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competencia_microcontenido');
    }
};
