<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\BrechasParaPdiInterface;
use App\Modules\Desarrollo\Application\Ports\ConfiguracionDePdiInterface;
use App\Modules\Desarrollo\Application\Ports\NombresDeReferenciaInterface;
use App\Modules\Desarrollo\Domain\Exceptions\DatosInvalidosDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDePdi;

/**
 * Sugiere los compromisos de un plan sin guardar nada: de la DNF más reciente en el tipo Mejora y de la última adecuación
 * al puesto objetivo en Carrera y Talento. Omite con aviso los elementos inactivos y, con la dimensión desactivada, los
 * conocimientos.
 */
final class PrecargarPdi
{
    public function __construct(
        private readonly BrechasParaPdiInterface $brechas,
        private readonly NombresDeReferenciaInterface $nombres,
        private readonly ConfiguracionDePdiInterface $configuracion,
    ) {}

    /**
     * @return array{origen: array{tipo: string, id: int, fecha: string|null}|null, compromisos: list<array<string, mixed>>, avisos: list<array{tipo: string, mensaje: string, cantidad: int}>}
     */
    public function ejecutar(int $tenantId, int $colaboradorId, TipoDePdi $tipo, ?int $puestoObjetivoId): array
    {
        if ($tipo->requierePuestoObjetivo() && $puestoObjetivoId === null) {
            throw DatosInvalidosDePdi::campo('puesto_objetivo_id', 'El puesto objetivo es obligatorio en los planes de Carrera y Talento.');
        }

        $fuente = $tipo === TipoDePdi::Mejora
            ? $this->brechas->ultimaDnf($tenantId, $colaboradorId)
            : $this->brechas->ultimaAdecuacion($tenantId, $colaboradorId, (int) $puestoObjetivoId);

        if ($fuente === null) {
            return ['origen' => null, 'compromisos' => [], 'avisos' => []];
        }

        $competencias = $this->nombres->competencias($this->idsDeCompetencias($fuente['brechas']));
        $compromisos = [];
        $inactivas = 0;
        $conocimientos = 0;

        foreach ($fuente['brechas'] as $b) {
            $dimension = $b['dimension'] ?? 'Competencia';

            if ($dimension === 'Conocimiento' && ! $this->configuracion->conocimientosConGrado()) {
                $conocimientos++;

                continue;
            }

            if ($dimension === 'Experiencia') {
                $compromisos[] = $this->experiencia($b);

                continue;
            }

            $id = (int) ($b['competencia_id'] ?? $b['elemento_id']);
            $ficha = $competencias[$id] ?? null;
            if ($ficha === null || ! $ficha['activa']) {
                $inactivas++;

                continue;
            }

            $nivelActual = $b['nivel_actual'] ?? null;
            $nivelObjetivo = $b['nivel_objetivo'] ?? null;
            $conNiveles = $nivelActual !== null && $nivelObjetivo !== null && $nivelActual !== $nivelObjetivo;
            $compromisos[] = [
                'dimension' => 'Competencia',
                'tipo_elemento' => 'Competencia',
                'elemento' => ['id' => $id, 'nombre' => $ficha['nombre']],
                'nivel_actual' => $conNiveles ? $nivelActual : null,
                'nivel_objetivo' => $conNiveles ? $nivelObjetivo : null,
                'descripcion' => null,
            ];
        }

        $avisos = [];
        if ($inactivas > 0) {
            $avisos[] = ['tipo' => 'inactivos_omitidos', 'mensaje' => "Se omitieron {$inactivas} elementos inactivos.", 'cantidad' => $inactivas];
        }
        if ($conocimientos > 0) {
            $avisos[] = ['tipo' => 'conocimientos_desactivados', 'mensaje' => 'La dimensión Conocimiento no está disponible; se omitieron '.$conocimientos.' brechas de conocimientos.', 'cantidad' => $conocimientos];
        }

        return ['origen' => $fuente['origen'], 'compromisos' => $compromisos, 'avisos' => $avisos];
    }

    /**
     * @param  list<array<string, mixed>>  $brechas
     * @return list<int>
     */
    private function idsDeCompetencias(array $brechas): array
    {
        $ids = [];
        foreach ($brechas as $b) {
            if (($b['dimension'] ?? 'Competencia') === 'Competencia') {
                $ids[] = (int) ($b['competencia_id'] ?? $b['elemento_id']);
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<string, mixed>  $b
     * @return array<string, mixed>
     */
    private function experiencia(array $b): array
    {
        $requerido = $b['requerido_anios'] ?? null;
        $actual = $b['valor_anios'] ?? null;
        $texto = $requerido === null ? 'Ganar la experiencia que exige el puesto objetivo.' : 'Alcanzar '.$this->anios((float) $requerido).' de experiencia'.($actual === null ? '.' : ' (actual: '.$this->anios((float) $actual).').');

        return [
            'dimension' => 'Experiencia',
            'tipo_elemento' => null,
            'elemento' => null,
            'nivel_actual' => null,
            'nivel_objetivo' => null,
            'descripcion' => $texto,
        ];
    }

    private function anios(float $valor): string
    {
        $texto = rtrim(rtrim(number_format($valor, 1, '.', ''), '0'), '.');

        return $texto.($texto === '1' ? ' año' : ' años');
    }
}
