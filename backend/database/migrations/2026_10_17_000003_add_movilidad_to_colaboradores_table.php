<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('colaboradores', function (Blueprint $table) {
            $table->string('movilidad_estado', 14)->default('NoInformada');
            $table->string('movilidad_observaciones', 2000)->nullable();
            $table->unsignedInteger('movilidad_version')->default(1);
            $table->timestamp('movilidad_actualizada_at')->nullable();
            $table->unsignedBigInteger('movilidad_actualizada_por')->nullable();
        });

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER colaboradores_movilidad_update BEFORE UPDATE OF movilidad_estado ON colaboradores
                BEGIN
                    SELECT RAISE(ABORT, 'estado de movilidad invalido')
                    WHERE NEW.movilidad_estado NOT IN ('Disponible','NoDisponible','NoInformada');
                END");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS colaboradores_movilidad_update');
        }
        Schema::table('colaboradores', function (Blueprint $table) {
            $table->dropColumn([
                'movilidad_estado', 'movilidad_observaciones', 'movilidad_version',
                'movilidad_actualizada_at', 'movilidad_actualizada_por',
            ]);
        });
    }
};
