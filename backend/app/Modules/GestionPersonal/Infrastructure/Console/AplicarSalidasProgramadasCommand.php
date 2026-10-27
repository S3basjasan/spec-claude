<?php

namespace App\Modules\GestionPersonal\Infrastructure\Console;

use App\Modules\GestionPersonal\Application\UseCases\AplicarSalidasProgramadas;
use Illuminate\Console\Command;

class AplicarSalidasProgramadasCommand extends Command
{
    protected $signature = 'offboarding:aplicar-salidas';

    protected $description = 'Aplica las salidas formalizadas cuya fecha efectiva ya llegó (identidad de sistema, idempotente; no reintenta incidencias).';

    public function handle(AplicarSalidasProgramadas $aplicar): int
    {
        $resumen = $aplicar->ejecutar();

        $this->info(sprintf(
            'Aplicadas: %d · incidencias: %d · omitidas: %d',
            $resumen['aplicadas'], $resumen['incidencias'], $resumen['omitidas'],
        ));

        return self::SUCCESS;
    }
}
