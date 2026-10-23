<?php

namespace App\Modules\Desarrollo\Infrastructure\Adapters;

use App\Modules\Desarrollo\Application\Ports\BrechasParaPdiInterface;
use App\Modules\Desarrollo\Application\UseCases\ConsultarAdecuacionesParaOtrosModulos;
use App\Modules\Desarrollo\Application\UseCases\ObtenerBrechasActivas;

/** Lee la última DNF y la última adecuación con los casos de uso del propio módulo (sin tocar sus tablas). */
final class BrechasParaPdiDesdeDnfYAdecuacion implements BrechasParaPdiInterface
{
    public function __construct(
        private readonly ObtenerBrechasActivas $dnf,
        private readonly ConsultarAdecuacionesParaOtrosModulos $adecuaciones,
    ) {}

    public function ultimaDnf(int $tenantId, int $colaboradorId): ?array
    {
        $ultima = $this->dnf->ultimaConBrechas($tenantId, $colaboradorId);
        if ($ultima === null) {
            return null;
        }

        return [
            'origen' => ['tipo' => 'dnf', 'id' => $ultima['dnf_id'], 'fecha' => $ultima['fecha']],
            'brechas' => array_map(fn (array $b) => [
                'competencia_id' => $b['competencia_id'],
                'nivel_actual' => $b['nivel_actual'],
                'nivel_objetivo' => $b['nivel_requerido'],
            ], $ultima['brechas']),
        ];
    }

    public function ultimaAdecuacion(int $tenantId, int $colaboradorId, int $puestoObjetivoId): ?array
    {
        $ultima = $this->adecuaciones->ultimaAdecuacion($tenantId, $colaboradorId, $puestoObjetivoId);
        if ($ultima === null) {
            return null;
        }

        return [
            'origen' => ['tipo' => 'adecuacion', 'id' => $ultima['adecuacion_id'], 'fecha' => $ultima['fecha']],
            'brechas' => array_map(fn (array $b) => [
                'dimension' => $b['dimension'],
                'tipo_elemento' => $b['tipo_elemento'],
                'elemento_id' => $b['elemento_id'],
                'nombre' => $b['nombre'],
                'nivel_actual' => $b['nivel_actual'],
                'nivel_objetivo' => $b['nivel_objetivo'],
                'valor_anios' => $b['valor_anios'],
                'requerido_anios' => $b['requerido_anios'],
            ], $ultima['brechas']),
        ];
    }
}
