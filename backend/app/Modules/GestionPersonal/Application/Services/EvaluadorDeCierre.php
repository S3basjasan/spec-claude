<?php

namespace App\Modules\GestionPersonal\Application\Services;

use App\Modules\GestionPersonal\Domain\Entities\Expediente;
use App\Modules\GestionPersonal\Domain\ValueObjects\EstadoDeSalida;
use Throwable;

/**
 * Qué impide confirmar el cierre administrativo: salida efectiva, tareas requeridas completas con evidencia
 * disponible y la tarea de entrevista resuelta. Lo no verificable cuenta como incumplido, nunca como cumplido.
 */
final class EvaluadorDeCierre
{
    public const SALIDA_NO_EFECTIVA = 'SALIDA_NO_EFECTIVA';

    public const TAREAS_PENDIENTES = 'TAREAS_PENDIENTES';

    public const ENTREVISTA_SIN_RESOLVER = 'ENTREVISTA_SIN_RESOLVER';

    public const EVIDENCIA_NO_DISPONIBLE = 'EVIDENCIA_NO_DISPONIBLE';

    public const YA_CERRADO = 'YA_CERRADO';

    public function __construct(private readonly ValidadorDeRespaldos $respaldos) {}

    /** @return list<string> */
    public function bloqueos(Expediente $expediente): array
    {
        if ($expediente->estaCerrado()) {
            return [self::YA_CERRADO];
        }

        $bloqueos = [];
        if ($expediente->estado !== EstadoDeSalida::Efectiva) {
            $bloqueos[] = self::SALIDA_NO_EFECTIVA;
        }

        foreach ($expediente->tareas as $tarea) {
            if ($tarea['tipo'] === 'Entrevista') {
                if ($tarea['estado'] !== 'Completada') {
                    $bloqueos[] = self::ENTREVISTA_SIN_RESOLVER;
                }

                continue;
            }
            if ($tarea['estado'] !== 'Completada') {
                $bloqueos[] = self::TAREAS_PENDIENTES;
            } elseif ($tarea['exige_evidencia'] && ! $this->evidenciaDisponible($expediente, $tarea['documento_ids'])) {
                $bloqueos[] = self::EVIDENCIA_NO_DISPONIBLE;
            }
        }

        return array_values(array_unique($bloqueos));
    }

    /** @param list<int> $documentoIds */
    private function evidenciaDisponible(Expediente $expediente, array $documentoIds): bool
    {
        try {
            return $this->respaldos->disponibles($expediente->tenantId, $expediente->colaboradorId, $documentoIds) >= 1;
        } catch (Throwable) {
            return false;
        }
    }
}
