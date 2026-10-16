<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\DTO\OpcionesDnfDTO;
use App\Modules\Desarrollo\Application\Ports\ColaboradoresEvaluablesInterface;
use App\Modules\Desarrollo\Application\Ports\NombresDeReferenciaInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Colaboradores y departamentos evaluables para el paso 1 del asistente. Si el origen de datos falla, devuelve listas
 * vacías (se registra sin datos personales).
 */
final class ObtenerOpcionesDnf
{
    public function __construct(
        private readonly ColaboradoresEvaluablesInterface $evaluables,
        private readonly NombresDeReferenciaInterface $nombres,
    ) {}

    public function ejecutar(int $tenantId): OpcionesDnfDTO
    {
        try {
            $colaboradores = $this->evaluables->evaluables($tenantId);
            $departamentos = $this->evaluables->departamentosConEvaluables($tenantId);
            $puestos = $this->nombres->puestos(array_values(array_unique(array_column($colaboradores, 'puesto_id'))));
        } catch (Throwable $e) {
            Log::warning('No se pudieron obtener las opciones de la DNF.', ['excepcion' => $e::class]);

            return new OpcionesDnfDTO([], []);
        }

        usort($colaboradores, fn (array $a, array $b) => strcasecmp($a['nombre'], $b['nombre']));

        return new OpcionesDnfDTO(
            array_map(fn (array $c) => [
                'id' => $c['colaborador_id'],
                'nombre' => $c['nombre'],
                'puesto' => $puestos[$c['puesto_id']]['nombre'] ?? 'No disponible',
                'departamento_id' => $c['unidad_id'],
            ], $colaboradores),
            array_map(fn (array $d) => [
                'id' => $d['id'],
                'nombre' => $d['nombre'],
                'colaboradores_evaluables' => $d['colaboradores_evaluables'],
            ], $departamentos),
        );
    }
}
