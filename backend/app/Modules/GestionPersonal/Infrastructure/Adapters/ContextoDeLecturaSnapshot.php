<?php

namespace App\Modules\GestionPersonal\Infrastructure\Adapters;

use App\Modules\GestionPersonal\Application\Ports\ContextoDeLecturaPort;
use Illuminate\Support\Facades\DB;

/**
 * Transacción de lectura sobre la conexión por defecto, donde leen ficha, vínculos, desarrollo y Estructura. En los
 * motores que lo admiten se fija además `REPEATABLE READ READ ONLY`; en SQLite una transacción de lectura da una vista
 * estable.
 */
final class ContextoDeLecturaSnapshot implements ContextoDeLecturaPort
{
    public function enSnapshot(callable $lectura): mixed
    {
        return DB::transaction(function () use ($lectura) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
            }

            return $lectura();
        });
    }
}
