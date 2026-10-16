<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias_documentales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('nombre', 100);
            $table->string('nombre_clave', 100);
            $table->string('nombre_busqueda', 100);
            $table->string('estado', 8)->default('Activa');
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by');
            $table->timestamps();

            $table->unique(['tenant_id', 'nombre_clave'], 'categorias_documentales_nombre_unico');
            $table->index(['tenant_id', 'estado', 'nombre_busqueda', 'id'], 'categorias_documentales_listado');
        });

        if (DB::getDriverName() === 'sqlite') {
            foreach (['INSERT', 'UPDATE'] as $evento) {
                DB::unprepared('CREATE TRIGGER categorias_documentales_validar_'.strtolower($evento)." BEFORE $evento ON categorias_documentales
                    BEGIN
                        SELECT RAISE(ABORT, 'estado de categoria invalido')
                        WHERE NEW.estado NOT IN ('Activa','Inactiva');
                    END");
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS categorias_documentales_validar_insert');
            DB::unprepared('DROP TRIGGER IF EXISTS categorias_documentales_validar_update');
        }
        Schema::dropIfExists('categorias_documentales');
    }
};
