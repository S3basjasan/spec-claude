<?php

namespace App\Modules\Desarrollo\Infrastructure\Adapters\Pruebas;

use App\Modules\Desarrollo\Application\Ports\ColaboradoresEvaluablesInterface;
use App\Modules\Estructura\Presentation\Facades\EstructuraFacade;
use App\Modules\GestionPersonal\Presentation\Facades\PersonalFacade;

/**
 * Solo para el entorno `e2e` (nunca se enlaza en otro entorno): usa las fichas de PersonalSeeder y los puestos y
 * unidades sembrados para que los E2E generen una DNF real sin Gestión de Personal GP-02.
 */
final class ColaboradoresEvaluablesDePrueba implements ColaboradoresEvaluablesInterface
{
    /** Tipo y número de identificación de las fichas sembradas que se consideran evaluables. */
    private const EVALUABLES = [['Cedula', '0102030405'], ['Cedula', '0912345678'], ['Pasaporte', 'AB123']];

    /** Fichas sembradas que se excluyen, con su motivo. */
    private const EXCLUIDOS = [['Otro', '0102-03', 'SinPuesto'], ['Cedula', '0000123456', 'SinVinculoVigente']];

    public function evaluablesPorIds(int $tenantId, array $ids): array
    {
        return $this->clasificar($tenantId, $ids);
    }

    public function evaluablesDeUnidad(int $tenantId, int $unidadId): array
    {
        $unidad = $this->unidad();

        return $unidad !== null && $unidad['id'] === $unidadId
            ? $this->clasificar($tenantId, null)
            : ['evaluables' => [], 'excluidos' => []];
    }

    public function evaluables(int $tenantId): array
    {
        return $this->clasificar($tenantId, null)['evaluables'];
    }

    public function departamentosConEvaluables(int $tenantId): array
    {
        $unidad = $this->unidad();

        return $unidad === null ? [] : [[
            'id' => $unidad['id'],
            'nombre' => $unidad['nombre'],
            'colaboradores_evaluables' => count($this->clasificar($tenantId, null)['evaluables']),
        ]];
    }

    /**
     * @param  list<int>|null  $ids
     * @return array{evaluables: list<array{colaborador_id: int, nombre: string, puesto_id: int, unidad_id: int|null}>, excluidos: list<array{colaborador_id: int, nombre: string, motivo: string}>}
     */
    private function clasificar(int $tenantId, ?array $ids): array
    {
        $puestos = EstructuraFacade::getPuestosActivos();
        $unidad = $this->unidad();
        $evaluables = [];
        $excluidos = [];

        foreach (self::EVALUABLES as $i => [$tipo, $numero]) {
            $ficha = PersonalFacade::buscarPorIdentificacion($tenantId, $tipo, $numero);
            if ($ficha === null || ($ids !== null && ! in_array((int) $ficha['id'], $ids, true))) {
                continue;
            }
            $nombre = trim($ficha['nombres'].' '.$ficha['apellidos']);
            if ($puestos === []) {
                $excluidos[] = ['colaborador_id' => (int) $ficha['id'], 'nombre' => $nombre, 'motivo' => 'SinPuesto'];

                continue;
            }
            $evaluables[] = [
                'colaborador_id' => (int) $ficha['id'],
                'nombre' => $nombre,
                'puesto_id' => (int) $puestos[$i % count($puestos)]['id'],
                'unidad_id' => $unidad['id'] ?? null,
            ];
        }

        foreach (self::EXCLUIDOS as [$tipo, $numero, $motivo]) {
            $ficha = PersonalFacade::buscarPorIdentificacion($tenantId, $tipo, $numero);
            if ($ficha !== null && ($ids === null || in_array((int) $ficha['id'], $ids, true))) {
                $excluidos[] = [
                    'colaborador_id' => (int) $ficha['id'],
                    'nombre' => trim($ficha['nombres'].' '.$ficha['apellidos']),
                    'motivo' => $motivo,
                ];
            }
        }

        return ['evaluables' => $evaluables, 'excluidos' => $excluidos];
    }

    /** @return array{id: int, nombre: string}|null */
    private function unidad(): ?array
    {
        $unidades = EstructuraFacade::getUnidadesActivas();

        return $unidades === [] ? null : ['id' => (int) $unidades[0]['id'], 'nombre' => (string) $unidades[0]['nombre']];
    }
}
