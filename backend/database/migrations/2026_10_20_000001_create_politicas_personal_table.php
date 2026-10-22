<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cabecera por tipo: punto de serialización de publicaciones y retiros y contador monótono de numeración.
        Schema::create('politicas_personal', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('tipo', 12);
            $table->unsignedInteger('ultimo_numero')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['tenant_id', 'tipo']);
        });

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER politicas_personal_validar_insert BEFORE INSERT ON politicas_personal
                BEGIN
                    SELECT RAISE(ABORT, 'tipo de politica invalido')
                    WHERE NEW.tipo NOT IN ('Permisos','Ausencias','Licencias','Vacaciones','Ascensos','Traslados','Offboarding');
                END");
            DB::unprepared("CREATE TRIGGER politicas_personal_validar_update BEFORE UPDATE ON politicas_personal
                BEGIN
                    SELECT RAISE(ABORT, 'tipo de politica invalido')
                    WHERE NEW.tipo NOT IN ('Permisos','Ausencias','Licencias','Vacaciones','Ascensos','Traslados','Offboarding')
                       OR NEW.tipo <> OLD.tipo
                       OR NEW.ultimo_numero < OLD.ultimo_numero;
                END");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS politicas_personal_validar_insert');
            DB::unprepared('DROP TRIGGER IF EXISTS politicas_personal_validar_update');
        }
        Schema::dropIfExists('politicas_personal');
    }
};
