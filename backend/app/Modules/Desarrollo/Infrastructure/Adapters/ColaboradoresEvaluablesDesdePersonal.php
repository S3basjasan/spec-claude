<?php

namespace App\Modules\Desarrollo\Infrastructure\Adapters;

use App\Modules\Desarrollo\Application\Ports\ColaboradoresEvaluablesInterface;
use App\Modules\Estructura\Presentation\Facades\EstructuraFacade;
use App\Modules\GestionPersonal\Presentation\Facades\PersonalFacade;

/**
 * Colaboradores evaluables por las Facades públicas de Gestión de Personal (vínculo vigente, puesto y unidad de su
 * asignación de hoy) y de Estructura (árbol de unidades para incluir subunidades).
 */
final class ColaboradoresEvaluablesDesdePersonal implements ColaboradoresEvaluablesInterface
{
    private const MAXIMO = 5000;

    public function evaluablesPorIds(int $tenantId, array $ids): array
    {
        return $this->clasificar($tenantId, array_values(array_unique(array_map('intval', $ids))), null);
    }

    public function evaluablesDeUnidad(int $tenantId, int $unidadId): array
    {
        return $this->clasificar($tenantId, null, $this->unidadesConSubunidades()[$unidadId] ?? [$unidadId]);
    }

    public function evaluables(int $tenantId): array
    {
        return $this->clasificar($tenantId, null, null)['evaluables'];
    }

    public function departamentosConEvaluables(int $tenantId): array
    {
        $evaluables = $this->evaluables($tenantId);
        $porUnidad = [];
        foreach ($evaluables as $e) {
            if ($e['unidad_id'] !== null) {
                $porUnidad[$e['unidad_id']] = ($porUnidad[$e['unidad_id']] ?? 0) + 1;
            }
        }

        $salida = [];
        foreach (EstructuraFacade::getArbolDeUnidades() as $unidad) {
            if ($unidad['estado'] !== 'Activa') {
                continue;
            }
            $total = 0;
            foreach ($this->unidadesConSubunidades()[(int) $unidad['id']] ?? [(int) $unidad['id']] as $id) {
                $total += $porUnidad[$id] ?? 0;
            }
            if ($total > 0) {
                $salida[] = ['id' => (int) $unidad['id'], 'nombre' => (string) $unidad['nombre'], 'colaboradores_evaluables' => $total];
            }
        }

        return $salida;
    }

    /**
     * @param  list<int>|null  $ids  Solo esos colaboradores (null = todos los vigentes del tenant).
     * @param  list<int>|null  $unidades  Solo los de esas unidades.
     * @return array{evaluables: list<array{colaborador_id: int, nombre: string, puesto_id: int, unidad_id: int|null}>, excluidos: list<array{colaborador_id: int, nombre: string, motivo: string}>}
     */
    private function clasificar(int $tenantId, ?array $ids, ?array $unidades): array
    {
        $hoy = now()->toDateString();
        $nombres = [];
        if ($ids === null) {
            $vigentes = [];
            foreach (PersonalFacade::buscarColaboradoresVigentes($tenantId, null, 1, self::MAXIMO, $hoy)['items'] as $item) {
                $vigentes[] = (int) $item['id'];
                $nombres[(int) $item['id']] = (string) $item['nombre'];
            }
        } else {
            $vigentes = $ids === [] ? [] : array_map('intval', PersonalFacade::vigentesPorIds($tenantId, $ids, $hoy));
            foreach ($ids === [] ? [] : PersonalFacade::getColaboradoresPorIds($tenantId, $ids) as $ficha) {
                $nombres[(int) $ficha['id']] = trim($ficha['nombres'].' '.$ficha['apellidos']);
            }
        }

        $puestos = $vigentes === [] ? [] : PersonalFacade::getPuestoActual($tenantId, $vigentes, $hoy);
        $unidadDe = $vigentes === [] ? [] : PersonalFacade::getDepartamentoActual($tenantId, $vigentes, $hoy);

        $evaluables = [];
        $excluidos = [];
        foreach ($vigentes as $id) {
            $unidadId = isset($unidadDe[$id]) ? (int) $unidadDe[$id] : null;
            if ($unidades !== null && ! in_array($unidadId, $unidades, true)) {
                continue;
            }
            if (($puestos[$id] ?? null) === null) {
                $excluidos[] = ['colaborador_id' => $id, 'nombre' => $nombres[$id] ?? '', 'motivo' => 'SinPuesto'];

                continue;
            }
            $evaluables[] = ['colaborador_id' => $id, 'nombre' => $nombres[$id] ?? '', 'puesto_id' => (int) $puestos[$id], 'unidad_id' => $unidadId];
        }
        foreach (array_diff($ids ?? [], $vigentes) as $id) {
            if (isset($nombres[$id])) {
                $excluidos[] = ['colaborador_id' => $id, 'nombre' => $nombres[$id], 'motivo' => 'SinVinculoVigente'];
            }
        }

        return ['evaluables' => $evaluables, 'excluidos' => $excluidos];
    }

    /**
     * @return array<int, list<int>> id de unidad => ella misma y todas sus descendientes
     */
    private function unidadesConSubunidades(): array
    {
        $unidades = EstructuraFacade::getArbolDeUnidades();
        $hijos = [];
        foreach ($unidades as $unidad) {
            if ($unidad['unidad_padre'] !== null) {
                $hijos[(int) $unidad['unidad_padre']['id']][] = (int) $unidad['id'];
            }
        }

        $incluir = function (int $id) use (&$incluir, $hijos): array {
            $lista = [$id];
            foreach ($hijos[$id] ?? [] as $hijo) {
                $lista = array_merge($lista, $incluir($hijo));
            }

            return $lista;
        };

        $salida = [];
        foreach ($unidades as $unidad) {
            $salida[(int) $unidad['id']] = $incluir((int) $unidad['id']);
        }

        return $salida;
    }
}
