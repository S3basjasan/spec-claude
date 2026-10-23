<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdi_hitos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('pdi_id')->constrained('pdis')->restrictOnDelete();
            $table->date('fecha');
            $table->string('descripcion', 300);
            $table->string('estado', 9)->default('Pendiente'); // Pendiente | Realizado
            $table->string('observaciones', 500)->nullable();
            $table->unsignedBigInteger('registrado_by')->nullable();
            $table->dateTime('registrado_at')->nullable();
            $table->timestamps();

            $table->index(['pdi_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdi_hitos');
    }
};
