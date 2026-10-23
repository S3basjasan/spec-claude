<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdis', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('codigo', 12); // PDI-0001, generado por el sistema
            $table->unsignedBigInteger('colaborador_id'); // referencia lógica a Gestión de Personal
            $table->string('tipo', 8); // Mejora | Carrera | Talento; inmutable
            $table->string('objetivo', 500);
            $table->unsignedBigInteger('puesto_objetivo_id')->nullable(); // referencia lógica a Estructura; obligatorio en Carrera y Talento
            $table->unsignedBigInteger('origen_dnf_id')->nullable(); // referencia lógica a DNF
            $table->unsignedBigInteger('origen_adecuacion_id')->nullable(); // referencia lógica a Adecuación
            $table->unsignedBigInteger('responsable_id'); // referencia lógica a un usuario Administrador
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->string('estado', 8)->default('Borrador'); // Borrador | Acordado | Cerrado
            $table->unsignedInteger('version')->default(1); // control optimista
            $table->dateTime('acuerdo_responsable_at')->nullable();
            $table->unsignedBigInteger('acuerdo_responsable_by')->nullable();
            $table->dateTime('aceptacion_colaborador_at')->nullable();
            $table->string('comentario_colaborador', 1000)->nullable();
            $table->string('resultado', 13)->nullable(); // BrechaCerrada | Parcial | NoCerrada
            $table->string('conclusion', 1000)->nullable();
            $table->dateTime('cerrado_at')->nullable();
            $table->unsignedBigInteger('cerrado_by')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by');
            $table->timestamps();

            $table->unique(['tenant_id', 'codigo']);
            $table->index(['tenant_id', 'colaborador_id', 'estado']);
            $table->index(['tenant_id', 'estado', 'fecha_fin']);
            $table->index(['tenant_id', 'responsable_id']);
        });

        // Un solo plan no Cerrado por colaborador y tipo (research R8). Laravel no expresa índices parciales.
        DB::statement("CREATE UNIQUE INDEX pdis_abierto_unico ON pdis (tenant_id, colaborador_id, tipo) WHERE estado <> 'Cerrado'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS pdis_abierto_unico');
        Schema::dropIfExists('pdis');
    }
};
