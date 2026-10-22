<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ruta_inscripciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('ruta_id')->constrained('rutas')->restrictOnDelete();
            // Referencia lógica a un colaborador de Gestión de Personal: sin clave foránea.
            $table->unsignedBigInteger('colaborador_id');
            $table->foreignId('nivel_actual_id')->nullable()->constrained('ruta_niveles')->restrictOnDelete();
            $table->foreignId('nivel_objetivo_id')->nullable()->constrained('ruta_niveles')->restrictOnDelete();
            $table->string('estado', 10)->default('Activa')->index(); // Activa | Finalizada | Retirada
            $table->string('motivo', 500)->nullable(); // obligatorio al retirar
            $table->unsignedBigInteger('resuelta_by')->nullable(); // nulo = sistema (p. ej. baja del colaborador)
            $table->dateTime('resuelta_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'colaborador_id']);
            $table->index(['ruta_id', 'estado']);
        });

        // Una sola inscripción Activa por colaborador y ruta; las Finalizadas y Retiradas no cuentan.
        DB::statement("CREATE UNIQUE INDEX ruta_inscripciones_una_activa ON ruta_inscripciones (ruta_id, colaborador_id) WHERE estado = 'Activa'");
    }

    public function down(): void
    {
        Schema::dropIfExists('ruta_inscripciones');
    }
};
