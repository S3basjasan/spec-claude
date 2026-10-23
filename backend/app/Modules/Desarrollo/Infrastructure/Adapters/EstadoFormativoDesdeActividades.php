<?php

namespace App\Modules\Desarrollo\Infrastructure\Adapters;

use App\Modules\Desarrollo\Application\Ports\EstadoFormativoDeAccionInterface;
use App\Modules\Desarrollo\Application\UseCases\ConsultarActividadesParaOtrosModulos;

/** Lee de Actividades Formativas la actividad de cada acción y la asistencia del colaborador (solo lectura). */
final class EstadoFormativoDesdeActividades implements EstadoFormativoDeAccionInterface
{
    public function __construct(private readonly ConsultarActividadesParaOtrosModulos $actividades) {}

    public function deColaborador(int $tenantId, int $colaboradorId, array $accionIds): array
    {
        $porAccion = [];
        foreach ($this->actividades->asistenciaDeColaborador($tenantId, $colaboradorId, $accionIds) as $fila) {
            $porAccion[(int) $fila['accion_id']] = [
                'actividad_id' => (int) $fila['actividad_id'],
                'estado' => (string) $fila['estado'],
                'asistencia' => (string) $fila['asistencia'],
            ];
        }

        return $porAccion;
    }
}
