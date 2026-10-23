<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\DepartamentoDeColaboradorInterface;
use App\Modules\Desarrollo\Application\Ports\RelojInterface;
use App\Modules\Desarrollo\Application\Services\ArmadorDeDetalleDePdi;
use App\Modules\Desarrollo\Domain\Entities\Pdi;
use App\Modules\Desarrollo\Domain\Repositories\RepositorioDePdiInterface;
use Throwable;

/**
 * Carta Gantt consolidada: las acciones y los hitos de los planes Acordados, paginados por plan, con filtros por tipo de
 * plan, responsable y departamento (FR de la historia 8).
 */
final class ObtenerGanttDePdi
{
    public function __construct(
        private readonly RepositorioDePdiInterface $repositorio,
        private readonly DepartamentoDeColaboradorInterface $departamentoDe,
        private readonly ArmadorDeDetalleDePdi $armador,
        private readonly RelojInterface $reloj,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros  tipo, responsable_id, departamento_id
     * @return array{data: list<array<string, mixed>>, meta: array<string, int>, hoy: string}
     */
    public function ejecutar(int $tenantId, int $usuarioId, array $filtros, int $pagina, int $porPagina): array
    {
        $hoy = $this->reloj->hoy($usuarioId)->format('Y-m-d');
        $planes = $this->repositorio->listar($tenantId, array_filter([
            'estado' => 'Acordado',
            'tipo' => $filtros['tipo'] ?? null,
            'responsable_id' => $filtros['responsable_id'] ?? null,
        ]));

        if (! empty($filtros['departamento_id'])) {
            $planes = $this->deDepartamento($planes, $tenantId, (int) $filtros['departamento_id'], $hoy);
        }

        $total = count($planes);
        $pagina = max(1, $pagina);
        $slice = array_slice($planes, ($pagina - 1) * $porPagina, $porPagina);

        return [
            'data' => array_map(fn (Pdi $p) => $this->fila($this->armador->detalle($p, true, $usuarioId)), $slice),
            'meta' => ['page' => $pagina, 'per_page' => $porPagina, 'total' => $total, 'last_page' => max(1, (int) ceil($total / $porPagina))],
            'hoy' => $hoy,
        ];
    }

    /**
     * @param  array<string, mixed>  $d
     * @return array<string, mixed>
     */
    private function fila(array $d): array
    {
        $acciones = [];
        foreach ($d['compromisos'] as $compromiso) {
            $titulo = $compromiso['elemento']['nombre'] ?? $compromiso['descripcion'] ?? $compromiso['dimension'];
            foreach ($compromiso['acciones'] as $a) {
                $acciones[] = [
                    'id' => $a['id'],
                    'compromiso' => $titulo,
                    'descripcion' => $a['descripcion'],
                    'tipo' => $a['tipo'],
                    'fecha_inicio' => $a['fecha_inicio'],
                    'fecha_fin' => $a['fecha_fin'],
                    'estado' => $a['estado'],
                ];
            }
        }

        return [
            'plan' => [
                'id' => $d['id'],
                'codigo' => $d['codigo'],
                'colaborador' => $d['colaborador'],
                'tipo' => $d['tipo'],
                'estado' => $d['estado'],
                'responsable' => $d['responsable'],
                'fecha_inicio' => $d['fecha_inicio'],
                'fecha_fin' => $d['fecha_fin'],
                'avance' => $d['avance'],
                'hitos_vencidos' => $d['hitos_vencidos'],
            ],
            'acciones' => $acciones,
            'hitos' => $d['hitos'],
        ];
    }

    /**
     * @param  list<Pdi>  $planes
     * @return list<Pdi>
     */
    private function deDepartamento(array $planes, int $tenantId, int $departamentoId, string $hoy): array
    {
        $ids = array_values(array_unique(array_map(fn (Pdi $p) => $p->colaboradorId, $planes)));
        if ($ids === []) {
            return [];
        }

        try {
            $unidades = $this->departamentoDe->actuales($tenantId, $ids, $hoy);
        } catch (Throwable) {
            return [];
        }

        return array_values(array_filter($planes, fn (Pdi $p) => ($unidades[$p->colaboradorId] ?? null) === $departamentoId));
    }
}
