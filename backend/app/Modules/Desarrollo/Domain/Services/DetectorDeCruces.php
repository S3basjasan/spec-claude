<?php

namespace App\Modules\Desarrollo\Domain\Services;

/** Detecta colaboradores inscritos en acciones con fechas que se solapan. Solo informa: nunca impide. */
final class DetectorDeCruces
{
    /**
     * @param  list<array{accion_id: int, plan_id: int, colaborador_id: int, inicio: string, fin: string}>  $inscripciones  acciones no canceladas con fechas, fechas `Y-m-d`
     * @param  list<int>|null  $accionesAnalizadas  si se da, solo se reportan cruces de estas acciones; si no, de todas
     * @return list<array{colaborador_id: int, accion_id: int, con_accion_id: int, con_plan_id: int}>
     */
    public function detectar(array $inscripciones, ?array $accionesAnalizadas = null): array
    {
        $porColaborador = [];
        foreach ($inscripciones as $i) {
            $porColaborador[$i['colaborador_id']][] = $i;
        }

        $avisos = [];
        foreach ($porColaborador as $colaboradorId => $lista) {
            foreach ($lista as $a) {
                if ($accionesAnalizadas !== null && ! in_array($a['accion_id'], $accionesAnalizadas, true)) {
                    continue;
                }
                foreach ($lista as $b) {
                    if ($a['accion_id'] === $b['accion_id']) {
                        continue;
                    }
                    if ($a['inicio'] <= $b['fin'] && $b['inicio'] <= $a['fin']) {
                        $avisos[] = [
                            'colaborador_id' => (int) $colaboradorId,
                            'accion_id' => $a['accion_id'],
                            'con_accion_id' => $b['accion_id'],
                            'con_plan_id' => $b['plan_id'],
                        ];
                    }
                }
            }
        }

        return $avisos;
    }
}
