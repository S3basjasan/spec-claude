<?php

namespace App\Modules\Desarrollo\Application\Services;

use App\Modules\Desarrollo\Application\Ports\RelojInterface;
use App\Modules\Desarrollo\Domain\Exceptions\PeriodoInvalido;
use App\Modules\Desarrollo\Domain\Services\CalculadorDePeriodo;
use App\Modules\Desarrollo\Domain\ValueObjects\FiltroReportes;

/** Convierte los filtros recibidos (ya validados en el límite de entrada) en el objeto de valor de los reportes. */
final class ResolvedorDeFiltros
{
    public function __construct(
        private readonly CalculadorDePeriodo $periodos,
        private readonly RelojInterface $reloj,
    ) {}

    /**
     * @param  array<string, mixed>  $entrada  fecha_desde, fecha_hasta, departamento_id, tipo, plan_id
     *
     * @throws PeriodoInvalido
     */
    public function resolver(array $entrada, int $usuarioId): FiltroReportes
    {
        $periodo = $this->periodos->resolver($entrada['fecha_desde'] ?? null, $entrada['fecha_hasta'] ?? null, $this->reloj->hoy($usuarioId));

        return new FiltroReportes(
            $periodo,
            isset($entrada['departamento_id']) ? (int) $entrada['departamento_id'] : null,
            isset($entrada['tipo']) && $entrada['tipo'] !== '' ? (string) $entrada['tipo'] : null,
            isset($entrada['plan_id']) ? (int) $entrada['plan_id'] : null,
        );
    }
}
