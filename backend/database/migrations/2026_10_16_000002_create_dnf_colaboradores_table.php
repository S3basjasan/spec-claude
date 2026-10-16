<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dnf_colaboradores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dnf_id')->constrained('dnf')->restrictOnDelete();
            // Referencias lógicas a Gestión de Personal (sin clave foránea).
            $table->unsignedBigInteger('colaborador_id')->index();
            $table->unsignedBigInteger('puesto_id'); // copia del momento
            $table->timestamps();

            $table->unique(['dnf_id', 'colaborador_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dnf_colaboradores');
    }
};
