<?php

namespace App\Modules\GestionPersonal\Infrastructure\Console;

use App\Modules\GestionPersonal\Application\UseCases\AplicarMovimientosProgramados;
use Illuminate\Console\Command;

class AplicarMovimientosProgramadosCommand extends Command
{
    protected $signature = 'movimientos:aplicar-programados';

    protected $description = 'Aplica los ascensos y traslados aprobados cuya fecha efectiva ya llegó (identidad de sistema, idempotente; no reintenta incidencias).';

    public function handle(AplicarMovimientosProgramados $aplicar): int
    {
        $resumen = $aplicar->ejecutar();

        $this->info(sprintf(
            'Aplicados: %d · incidencias: %d · omitidos: %d',
            $resumen['aplicados'], $resumen['incidencias'], $resumen['omitidos'],
        ));

        return self::SUCCESS;
    }
}
