<?php

namespace App\Modules\GestionPersonal\Domain\Services;

use App\Modules\GestionPersonal\Domain\ValueObjects\ResultadoDeEvaluacion;
use DateTimeImmutable;
use Throwable;

/**
 * Verifica la evidencia de evaluación frente al puesto destino: identifica colaborador y puesto, trae requisitos,
 * momento y resultado, corresponde al destino y no está vencida. No compara contra umbrales ni porcentajes, y una
 * evaluación válida es evidencia para decidir, no una aprobación. Todo lo que falte es «no verificable». PHP puro.
 */
final class VerificadorDeEvaluacion
{
    /**
     * @param  array{estado?: mixed, contexto?: mixed}  $respuesta  Lo devuelto por el puerto de evaluación.
     * @return array{estado: ResultadoDeEvaluacion, motivo: string|null, contexto: array<string, mixed>|null}
     */
    public function verificar(array $respuesta, int $colaboradorId, ?int $puestoDestinoId, DateTimeImmutable $instante): array
    {
        $estado = ResultadoDeEvaluacion::tryFrom((string) ($respuesta['estado'] ?? ''));
        $contexto = is_array($respuesta['contexto'] ?? null) ? $respuesta['contexto'] : null;

        if ($estado === ResultadoDeEvaluacion::NoDisponible) {
            return $this->resultado(ResultadoDeEvaluacion::NoDisponible, 'No existe una evaluación disponible frente al puesto destino.', null);
        }
        if ($estado !== ResultadoDeEvaluacion::Valida || $contexto === null) {
            return $this->resultado(ResultadoDeEvaluacion::NoVerificable, 'La evaluación no pudo verificarse.', null);
        }

        $requisitos = $contexto['requisitos'] ?? null;
        $faltantes = [];
        if (! is_int($contexto['colaborador_id'] ?? null) || $contexto['colaborador_id'] !== $colaboradorId) {
            $faltantes[] = 'la identidad del colaborador';
        }
        if ($puestoDestinoId === null || ! is_int($contexto['puesto_id'] ?? null) || $contexto['puesto_id'] !== $puestoDestinoId) {
            $faltantes[] = 'el puesto destino';
        }
        if (! is_array($requisitos) || $requisitos === []) {
            $faltantes[] = 'los requisitos considerados';
        }
        if (! is_string($contexto['resultado'] ?? null) || trim($contexto['resultado']) === '') {
            $faltantes[] = 'el resultado';
        }
        $momento = $this->fecha($contexto['evaluada_en'] ?? null);
        if ($momento === null) {
            $faltantes[] = 'el momento de la evaluación';
        } elseif ($momento > $instante) {
            $faltantes[] = 'un momento anterior al actual';
        }
        if (isset($contexto['vigente_hasta'])) {
            $hasta = $this->fecha($contexto['vigente_hasta']);
            if ($hasta === null || $hasta < $instante) {
                return $this->resultado(ResultadoDeEvaluacion::NoVerificable, 'La evaluación ya no está vigente.', null);
            }
        }

        if ($faltantes !== []) {
            return $this->resultado(ResultadoDeEvaluacion::NoVerificable, 'La evaluación no acredita '.implode(', ', $faltantes).'.', null);
        }

        return $this->resultado(ResultadoDeEvaluacion::Valida, null, $contexto);
    }

    /**
     * @param  array<string, mixed>|null  $contexto
     * @return array{estado: ResultadoDeEvaluacion, motivo: string|null, contexto: array<string, mixed>|null}
     */
    private function resultado(ResultadoDeEvaluacion $estado, ?string $motivo, ?array $contexto): array
    {
        return ['estado' => $estado, 'motivo' => $motivo, 'contexto' => $contexto];
    }

    private function fecha(mixed $valor): ?DateTimeImmutable
    {
        if (! is_string($valor) || $valor === '') {
            return null;
        }
        try {
            return new DateTimeImmutable($valor);
        } catch (Throwable) {
            return null;
        }
    }
}
