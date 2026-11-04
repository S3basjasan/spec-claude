<?php

namespace App\Modules\Compensaciones\Infrastructure\Console;

use App\Modules\Compensaciones\Application\Ports\UnidadesPort;
use App\Modules\Compensaciones\Application\Services\AuditoriaDeCategoria;
use App\Modules\Compensaciones\Domain\ValueObjects\UnidadesAsignadas;
use App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Models\CategoriaReconocimientoModel;
use Illuminate\Console\Command;

/**
 * 01b.4: asigna todas las unidades activas a las categorías que todavía no tienen unidades (las anteriores a esta
 * función). Idempotente: no toca las que ya tienen unidades. Cada asignación queda en la auditoría de la categoría.
 */
class AsignarUnidadesIniciales extends Command
{
    protected $signature = 'compensaciones:asignar-unidades-iniciales';

    protected $description = 'Asigna todas las unidades activas a las categorías de reconocimiento que no tienen unidades';

    public function handle(UnidadesPort $unidades, AuditoriaDeCategoria $auditoria): int
    {
        $pendientes = CategoriaReconocimientoModel::query()
            ->where(fn ($q) => $q->whereNull('unidades_ids')->orWhere('unidades_ids', '[]'))
            ->get();

        if ($pendientes->isEmpty()) {
            $this->info('Ninguna categoría necesita unidades.');

            return self::SUCCESS;
        }

        $ids = array_map(fn (array $u) => $u['id'], $unidades->activas());

        if ($ids === []) {
            $this->warn('No hay unidades activas: no se asignó ninguna.');

            return self::FAILURE;
        }

        $asignadas = new UnidadesAsignadas($ids);

        foreach ($pendientes as $categoria) {
            $categoria->unidades_ids = $asignadas->ids();
            $categoria->saveQuietly();
            $auditoria->alAsignarUnidades((int) $categoria->id, null, $asignadas, null);
        }

        $this->info("Se asignaron {$asignadas->comoJson()} a {$pendientes->count()} categoría(s).");

        return self::SUCCESS;
    }
}
