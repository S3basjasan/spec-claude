<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('colaborador_cuentas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id')->unique();
            $table->foreignId('colaborador_id')->unique()->constrained('colaboradores');
            $table->boolean('puede_consultar_ficha')->default(false);
            $table->boolean('puede_consultar_documentos')->default(false);
            $table->boolean('puede_exportar')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('colaborador_cuentas');
    }
};
