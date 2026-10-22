<?php

namespace App\Modules\Desarrollo\Domain\Services;

/**
 * Diferencial de un nivel respecto del anterior, calculado sin almacenarlo. Solo compara competencias (por grado, de A,
 * el más alto, a D, el más bajo) y los años de experiencia mínima. Los requisitos de texto del puesto no entran al cálculo.
 */
final class CalculadorDeDiferencial
{
    private const RANGO = ['D' => 1, 'C' => 2, 'B' => 3, 'A' => 4];

    /**
     * @param  array{competencias: list<array{competencia_id: int, codigo: string, nombre: string, grado: string}>, experiencia_anios: float|null}|null  $anterior  nulo en el nivel 1
     * @param  array{competencias: list<array{competencia_id: int, codigo: string, nombre: string, grado: string}>, experiencia_anios: float|null}  $actual
     * @return array{competencias: array<int, array{tipo: string, antes?: string, delta?: int}|null>, quitadas: list<array{competencia_id: int, codigo: string, nombre: string, grado_anterior: string}>, experiencia_anios: float|null}
     */
    public function entre(?array $anterior, array $actual): array
    {
        $competencias = [];

        if ($anterior === null) {
            foreach ($actual['competencias'] as $c) {
                $competencias[$c['competencia_id']] = null;
            }

            return ['competencias' => $competencias, 'quitadas' => [], 'experiencia_anios' => null];
        }

        $previas = [];
        foreach ($anterior['competencias'] as $c) {
            $previas[$c['competencia_id']] = $c;
        }

        $vigentes = [];
        foreach ($actual['competencias'] as $c) {
            $vigentes[$c['competencia_id']] = true;
            $previa = $previas[$c['competencia_id']] ?? null;
            if ($previa === null) {
                $competencias[$c['competencia_id']] = ['tipo' => 'nuevo'];

                continue;
            }
            $delta = $this->rango($c['grado']) - $this->rango($previa['grado']);
            $competencias[$c['competencia_id']] = match (true) {
                $delta > 0 => ['tipo' => 'sube', 'antes' => $previa['grado'], 'delta' => $delta],
                $delta < 0 => ['tipo' => 'baja', 'antes' => $previa['grado'], 'delta' => $delta],
                default => ['tipo' => 'igual'],
            };
        }

        $quitadas = [];
        foreach ($previas as $id => $previa) {
            if (! isset($vigentes[$id])) {
                $quitadas[] = ['competencia_id' => $id, 'codigo' => $previa['codigo'], 'nombre' => $previa['nombre'], 'grado_anterior' => $previa['grado']];
            }
        }

        $experiencia = ($anterior['experiencia_anios'] !== null && $actual['experiencia_anios'] !== null)
            ? round($actual['experiencia_anios'] - $anterior['experiencia_anios'], 1)
            : null;

        return ['competencias' => $competencias, 'quitadas' => $quitadas, 'experiencia_anios' => $experiencia];
    }

    private function rango(string $grado): int
    {
        return self::RANGO[$grado] ?? 0;
    }
}
