<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\AccionesDePlanInterface;
use App\Modules\Desarrollo\Application\Services\ArmadorDeActividades;
use App\Modules\Desarrollo\Domain\Repositories\ActividadRepositoryInterface;
use App\Modules\Desarrollo\Domain\ValueObjects\AccionDeActividad;

/** Acciones que aún pueden recibir una actividad: no canceladas, de planes Ejecutando y sin actividad. */
final class ListarAccionesDisponibles
{
    public function __construct(
        private readonly ActividadRepositoryInterface $actividades,
        private readonly AccionesDePlanInterface $acciones,
        private readonly ArmadorDeActividades $armador,
    ) {}

    /**
     * @return array{data: list<array<string, mixed>>, meta: array{page: int, per_page: int, total: int, last_page: int}}
     */
    public function ejecutar(int $tenantId, ?int $planId, ?string $q, int $pagina, int $porPagina): array
    {
        $resultado = $this->acciones->elegibles($tenantId, $this->actividades->idsDeAccionesConActividad($tenantId), $planId, $q, $pagina, $porPagina);
        $idsCompetencias = array_values(array_unique(array_merge([], ...array_map(fn (AccionDeActividad $a) => $a->competenciaIds, $resultado['items']))));
        $competencias = $this->armador->nombresDeCompetencias($idsCompetencias);

        return [
            'data' => array_map(fn (AccionDeActividad $a) => [
                'accion_id' => $a->accionId,
                'nombre' => $a->nombre,
                'tipo' => $a->tipo?->value,
                'plan' => ['id' => $a->planId, 'codigo' => $a->planCodigo, 'nombre' => $a->planNombre],
                'competencias' => array_map(fn (int $id) => ['id' => $id, 'nombre' => $competencias[$id] ?? ArmadorDeActividades::NO_DISPONIBLE], $a->competenciaIds),
                'fecha_inicio' => $a->fechaInicio?->format('Y-m-d'),
                'fecha_fin' => $a->fechaFin?->format('Y-m-d'),
                'formador' => $a->formador,
                'capacidad' => $a->capacidadMaxima,
                'inscritos' => $a->inscritos(),
            ], $resultado['items']),
            'meta' => ['page' => $pagina, 'per_page' => $porPagina, 'total' => $resultado['total'], 'last_page' => max(1, (int) ceil($resultado['total'] / $porPagina))],
        ];
    }
}
