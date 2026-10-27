<?php

namespace App\Modules\GestionPersonal\Domain\Services;

/**
 * Promedios y frecuencias deterministas de las entrevistas completadas. Un grupo menor al mínimo se oculta por
 * completo para no identificar participantes; nunca se calculan índices predictivos ni relaciones causales. PHP puro.
 */
final class CalculadorDeAgregados
{
    public function __construct(private readonly CuestionarioDeSalida $cuestionario) {}

    /**
     * @param  list<array{entrevista_id: int, pregunta_id: string, tipo: string, valor: int|null}>  $respuestas  De entrevistas completadas.
     * @return array<string, mixed>
     */
    public function calcular(int $entrevistasCompletadas, array $respuestas, int $minimo): array
    {
        if ($entrevistasCompletadas < $minimo) {
            return ['oculto' => true, 'minimo' => $minimo, 'entrevistas' => null, 'dimensiones' => [], 'categorias' => []];
        }

        $dimensiones = [];
        foreach ($this->cuestionario->dimensiones() as $id => $dimension) {
            $valores = array_values(array_map(fn (array $r) => (int) $r['valor'], array_filter($respuestas, fn (array $r) => $r['pregunta_id'] === $id && $r['valor'] !== null)));
            $distribucion = array_fill(1, 5, 0);
            foreach ($valores as $v) {
                $distribucion[$v]++;
            }
            $dimensiones[] = [
                'dimension' => $dimension['nombre'],
                'respondieron' => count($valores),
                'promedio' => count($valores) >= $minimo ? round(array_sum($valores) / count($valores), 2) : null,
                'distribucion' => count($valores) >= $minimo ? $distribucion : null,
            ];
        }

        $categorias = [];
        foreach ($this->cuestionario->categorias() as $id => $categoria) {
            $cantidad = count(array_filter($respuestas, fn (array $r) => $r['pregunta_id'] === $id && $r['tipo'] === 'categoria'));
            $categorias[] = ['categoria' => $categoria['nombre'], 'menciones' => $cantidad >= $minimo ? $cantidad : null];
        }

        return ['oculto' => false, 'minimo' => $minimo, 'entrevistas' => $entrevistasCompletadas, 'dimensiones' => $dimensiones, 'categorias' => $categorias];
    }
}
