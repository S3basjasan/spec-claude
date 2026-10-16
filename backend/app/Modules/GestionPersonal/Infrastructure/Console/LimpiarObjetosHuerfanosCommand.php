<?php

namespace App\Modules\GestionPersonal\Infrastructure\Console;

use App\Modules\GestionPersonal\Application\UseCases\LimpiarObjetosHuerfanos;
use Illuminate\Console\Command;

class LimpiarObjetosHuerfanosCommand extends Command
{
    protected $signature = 'documentos:limpiar-huerfanos {--horas= : Antigüedad mínima en horas (por defecto la configurada)}';

    protected $description = 'Elimina los objetos de documentos sin referencia de más de 24 horas (no toca documentos publicados ni archivados).';

    public function handle(LimpiarObjetosHuerfanos $limpiar): int
    {
        $horas = $this->option('horas') !== null
            ? (int) $this->option('horas')
            : (int) config('hrms.gestion_personal.documentos.huerfanos_horas', 24);

        $resumen = $limpiar->ejecutar($horas);

        $this->info(sprintf(
            'Revisados: %d · eliminados: %d · conservados: %d · fallidos: %d',
            $resumen['revisados'], $resumen['eliminados'], $resumen['conservados'], $resumen['fallidos'],
        ));

        return $resumen['fallidos'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
