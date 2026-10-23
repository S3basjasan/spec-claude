<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\AccionesVinculablesInterface;
use App\Modules\Desarrollo\Domain\ValueObjects\AccionDeActividad;

/**
 * Acciones del Plan de Formación que se pueden vincular a una acción del PDI: de planes Aprobado o Ejecutando donde el
 * colaborador es participante, sin canceladas (FR-019). Solo lectura.
 */
final class ListarAccionesVinculables
{
    public function __construct(private readonly AccionesVinculablesInterface $vinculables) {}

    /**
     * @return list<array{plan_accion_id: int, plan_id: int, plan_codigo: string, plan_nombre: string, plan_estado: string, nombre: string, tipo: string|null, fecha_inicio: string|null, fecha_fin: string|null, formador: string|null}>
     */
    public function ejecutar(int $tenantId, int $colaboradorId, ?string $texto = null): array
    {
        $acciones = $this->vinculables->deColaborador($tenantId, $colaboradorId);
        if ($texto !== null && trim($texto) !== '') {
            $buscado = mb_strtolower(trim($texto));
            $acciones = array_values(array_filter($acciones, fn (AccionDeActividad $a) => str_contains(mb_strtolower($a->nombre.' '.$a->planNombre), $buscado)));
        }

        return array_map(fn (AccionDeActividad $a) => [
            'plan_accion_id' => $a->accionId,
            'plan_id' => $a->planId,
            'plan_codigo' => $a->planCodigo,
            'plan_nombre' => $a->planNombre,
            'plan_estado' => $a->planEstado->value,
            'nombre' => $a->nombre,
            'tipo' => $a->tipo?->value,
            'fecha_inicio' => $a->fechaInicio?->format('Y-m-d'),
            'fecha_fin' => $a->fechaFin?->format('Y-m-d'),
            'formador' => $a->formador,
        ], $acciones);
    }
}
