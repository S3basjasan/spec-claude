<?php

namespace App\Modules\Desarrollo\Infrastructure\Console;

use App\Modules\Desarrollo\Application\UseCases\ConciliarBajasDeInscripciones;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\RutaInscripcionModel;
use Illuminate\Console\Command;

/** Retira las inscripciones Activas de quien ya no tiene vínculo laboral vigente (FR-021a), en todos los tenants. */
class ConciliarBajasRutasCommand extends Command
{
    protected $signature = 'rutas:conciliar-bajas';

    protected $description = 'Retira las inscripciones activas a rutas de carrera de los colaboradores que ya no tienen vínculo laboral vigente.';

    public function handle(ConciliarBajasDeInscripciones $conciliar): int
    {
        $retiradas = 0;
        $tenants = RutaInscripcionModel::query()->where('estado', 'Activa')->distinct()->pluck('tenant_id');
        foreach ($tenants as $tenantId) {
            $retiradas += $conciliar->ejecutar((int) $tenantId);
        }

        $this->info("Inscripciones retiradas por baja: {$retiradas}");

        return self::SUCCESS;
    }
}
