<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\PuestosParaRutasInterface;
use App\Modules\Desarrollo\Application\Services\ArmadorDeAdecuaciones;
use App\Modules\Desarrollo\Domain\Entities\Adecuacion;
use App\Modules\Desarrollo\Domain\Exceptions\ComparacionInvalida;
use App\Modules\Desarrollo\Domain\Exceptions\FuenteNoDisponibleParaAdecuacion;
use App\Modules\Desarrollo\Domain\Repositories\AdecuacionesRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\CalculadorDeCumplimiento;
use App\Modules\Desarrollo\Domain\Services\CalculadorDePuntaje;
use App\Modules\Desarrollo\Domain\ValueObjects\PesosPorDimension;
use App\Modules\Desarrollo\Domain\ValueObjects\Preparacion;
use Throwable;

/**
 * Compara candidatos para un puesto con la adecuación vigente de cada uno (FR-023 a FR-027). El puntaje es la suma de los
 * pesos por el cumplimiento de cada dimensión, con las dimensiones no evaluadas excluidas y los pesos reescalados. Los
 * candidatos con preparación Incompleta se devuelven aparte y sin puntaje, y quienes no tienen adecuación para el puesto
 * quedan señalados. El resultado se calcula en el momento y no se guarda.
 */
final class CompararCandidatos
{
    public const MINIMO = 2;

    public const MAXIMO = 20;

    public function __construct(
        private readonly AdecuacionesRepositoryInterface $adecuaciones,
        private readonly PuestosParaRutasInterface $puestos,
        private readonly ArmadorDeAdecuaciones $armador,
        private readonly CalculadorDeCumplimiento $cumplimiento,
        private readonly CalculadorDePuntaje $puntaje,
    ) {}

    /**
     * @param  list<int>  $colaboradorIds
     * @param  array{competencias: int, conocimientos: int, experiencia: int}|null  $pesosDeLaConsulta
     * @return array<string, mixed>
     */
    public function ejecutar(int $tenantId, int $puestoObjetivoId, array $colaboradorIds, ?array $pesosDeLaConsulta): array
    {
        if (count($colaboradorIds) < self::MINIMO || count($colaboradorIds) > self::MAXIMO || count(array_unique($colaboradorIds)) !== count($colaboradorIds)) {
            throw ComparacionInvalida::candidatos();
        }

        $pesos = $pesosDeLaConsulta === null
            ? $this->adecuaciones->obtenerParametros($tenantId)->pesos
            : new PesosPorDimension($pesosDeLaConsulta['competencias'], $pesosDeLaConsulta['conocimientos'], $pesosDeLaConsulta['experiencia']);

        $puesto = $this->puesto($puestoObjetivoId);

        /** @var array<int, Adecuacion> $vigentes */
        $vigentes = [];
        foreach ($this->adecuaciones->vigentes($tenantId, ['puesto_objetivo_id' => $puestoObjetivoId, 'colaborador_ids' => $colaboradorIds]) as $a) {
            $vigentes[$a->colaboradorId] = $a;
        }

        $fuentes = [];
        $contexto = $this->armador->contexto(
            $tenantId,
            $colaboradorIds,
            array_values(array_filter(array_map(fn (Adecuacion $a) => $a->puestoActualId, $vigentes))),
            $fuentes,
        );
        $nombre = fn (int $id) => $contexto['colaboradores'][$id] ?? 'No disponible';

        $ranking = [];
        $incompletos = [];
        $sinAdecuacion = [];
        foreach ($colaboradorIds as $colaboradorId) {
            $a = $vigentes[$colaboradorId] ?? null;
            $colaborador = ['id' => $colaboradorId, 'nombre' => $nombre($colaboradorId)];

            if ($a === null) {
                $sinAdecuacion[] = ['colaborador' => $colaborador];

                continue;
            }
            if ($a->preparacion === Preparacion::Incompleta) {
                $incompletos[] = ['colaborador' => $colaborador, 'adecuacion_id' => $a->id];

                continue;
            }

            $cumplimientos = $this->cumplimiento->porDimension($a->resultados);
            $ranking[] = [
                'colaborador' => $colaborador,
                'adecuacion_id' => $a->id,
                'puntaje' => $this->puntaje->calcular($cumplimientos, $pesos) ?? 0.0,
                'cumplimiento_por_dimension' => $this->armador->redondear($cumplimientos),
                'preparacion' => $a->preparacion->value,
                'preparacion_etiqueta' => $a->preparacion->etiqueta(),
                'brechas' => $a->cantidadDeBrechas(),
                'puesto_actual' => $a->puestoActualId === null ? null : ($contexto['puestos'][$a->puestoActualId] ?? null),
            ];
        }

        usort($ranking, fn (array $x, array $y) => [$y['puntaje'], $x['brechas'], $x['colaborador']['nombre']] <=> [$x['puntaje'], $y['brechas'], $y['colaborador']['nombre']]);
        foreach ($ranking as $i => &$fila) {
            $fila = ['posicion' => $i + 1] + $fila;
        }
        unset($fila);

        return [
            'puesto_objetivo' => $puesto,
            'pesos' => $pesos->comoArreglo(),
            'ranking' => $ranking,
            'incompletos' => $incompletos,
            'sin_adecuacion' => $sinAdecuacion,
            'fuentes_no_disponibles' => array_values(array_unique($fuentes)),
        ];
    }

    /** @return array{id: int, nombre: string} */
    private function puesto(int $puestoId): array
    {
        try {
            $perfil = $this->puestos->perfil($puestoId);
        } catch (Throwable) {
            throw new FuenteNoDisponibleParaAdecuacion;
        }

        if ($perfil === null) {
            throw ComparacionInvalida::puesto();
        }

        return ['id' => $puestoId, 'nombre' => (string) $perfil['puesto']['nombre']];
    }
}
