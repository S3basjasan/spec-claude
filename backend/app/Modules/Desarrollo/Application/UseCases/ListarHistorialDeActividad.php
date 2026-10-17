<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Services\ArmadorDeActividades;
use App\Modules\Desarrollo\Domain\Exceptions\ActividadNoEncontrada;
use App\Modules\Desarrollo\Domain\Repositories\ActividadRepositoryInterface;

/** Historial de cambios de asistencia y calificación de una actividad, del más reciente al más antiguo. */
final class ListarHistorialDeActividad
{
    public function __construct(
        private readonly ActividadRepositoryInterface $actividades,
        private readonly ArmadorDeActividades $armador,
    ) {}

    /**
     * @return array{data: list<array<string, mixed>>, meta: array{page: int, per_page: int, total: int, last_page: int}}
     *
     * @throws ActividadNoEncontrada
     */
    public function ejecutar(int $tenantId, int $id, int $pagina, int $porPagina): array
    {
        $this->actividades->obtener($tenantId, $id) ?? throw new ActividadNoEncontrada;

        $resultado = $this->actividades->historial($id, $pagina, $porPagina);
        $usuarios = $this->armador->nombresDeUsuarios(array_column($resultado['items'], 'usuario_id'));
        $colaboradores = $this->armador->nombresDeColaboradores($tenantId, array_values(array_unique(array_column($resultado['items'], 'colaborador_id'))));

        return [
            'data' => array_map(fn (array $h) => [
                'id' => $h['id'],
                'fecha' => $h['fecha']?->format('c'),
                'usuario' => $usuarios[$h['usuario_id']] ?? ArmadorDeActividades::NO_DISPONIBLE,
                'colaborador_id' => $h['colaborador_id'],
                'participante' => $colaboradores[$h['colaborador_id']] ?? ArmadorDeActividades::NO_DISPONIBLE,
                'asistencia_anterior' => $h['asistencia_anterior']?->value,
                'asistencia_nueva' => $h['asistencia_nueva']?->value,
                'calificacion_anterior' => $h['calificacion_anterior'],
                'calificacion_nueva' => $h['calificacion_nueva'],
            ], $resultado['items']),
            'meta' => ['page' => $pagina, 'per_page' => $porPagina, 'total' => $resultado['total'], 'last_page' => max(1, (int) ceil($resultado['total'] / $porPagina))],
        ];
    }
}
