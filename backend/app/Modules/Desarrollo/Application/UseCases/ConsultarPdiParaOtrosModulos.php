<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\RelojInterface;
use App\Modules\Desarrollo\Application\Services\ResolvedorDeEstadoDeAcciones;
use App\Modules\Desarrollo\Domain\Entities\CompromisoDePdi;
use App\Modules\Desarrollo\Domain\Entities\Pdi;
use App\Modules\Desarrollo\Domain\Repositories\RepositorioDePdiInterface;
use App\Modules\Desarrollo\Domain\Services\CalculadorDeAvance;
use App\Modules\Desarrollo\Domain\Services\HitosVencidos;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\ResultadoDeCompromiso;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDePdi;
use DateTimeImmutable;
use Throwable;

/**
 * Consultas de solo lectura que ofrece la Facade a Reportes y a Adecuación. Devuelven cabeceras y cifras agregadas: sin
 * compromisos, acciones, comentarios ni quién los creó.
 */
final class ConsultarPdiParaOtrosModulos
{
    public function __construct(
        private readonly RepositorioDePdiInterface $repositorio,
        private readonly ResolvedorDeEstadoDeAcciones $resolvedor,
        private readonly CalculadorDeAvance $avance,
        private readonly HitosVencidos $vencidos,
        private readonly RelojInterface $reloj,
    ) {}

    /**
     * Planes visibles para el colaborador y no Cerrados (Borrador con acuerdo del responsable o Acordado) con su avance.
     *
     * @return list<array{pdi_id: int, codigo: string, tipo: string, estado: string, objetivo: string, fecha_inicio: string, fecha_fin: string, avance: int|null, hitos_vencidos: int}>
     */
    public function planesActivos(int $tenantId, int $colaboradorId): array
    {
        $hoy = $this->hoy();
        $activos = array_filter(
            $this->repositorio->deColaborador($tenantId, $colaboradorId),
            fn (Pdi $p) => $p->estado !== EstadoDePdi::Cerrado && $p->visibleParaColaborador(),
        );

        return array_values(array_map(fn (Pdi $p) => [
            'pdi_id' => (int) $p->id,
            'codigo' => (string) $p->codigo,
            'tipo' => $p->tipo->value,
            'estado' => $p->estado->value,
            'objetivo' => $p->objetivo,
            'fecha_inicio' => $p->periodo->inicio->format('Y-m-d'),
            'fecha_fin' => $p->periodo->fin->format('Y-m-d'),
            'avance' => $this->avance->calcular(array_map(fn (array $e) => $e['estado'], array_values($this->resolvedor->resolver($p, $hoy)))),
            'hitos_vencidos' => $this->vencidos->contar($p->hitos, $hoy),
        ], $activos));
    }

    /**
     * Cabeceras de planes por filtros: tipo, estado, resultado, fecha_desde, fecha_hasta (sobre la fecha de fin) y
     * colaborador_id.
     *
     * @param  array<string, mixed>  $filtros
     * @return list<array{pdi_id: int, codigo: string, colaborador_id: int, tipo: string, estado: string, resultado: string|null, fecha_inicio: string, fecha_fin: string, cerrado_at: string|null, compromisos: int, compromisos_cerrados: int}>
     */
    public function pdiPorFiltros(int $tenantId, array $filtros = []): array
    {
        $paraElRepositorio = array_filter(
            array_intersect_key($filtros, array_flip(['tipo', 'estado', 'fecha_desde', 'fecha_hasta', 'colaborador_id'])),
            fn ($v) => $v !== null && $v !== '',
        );
        $planes = $this->repositorio->listar($tenantId, $paraElRepositorio);

        if (! empty($filtros['resultado'])) {
            $planes = array_values(array_filter($planes, fn (Pdi $p) => $p->resultado?->value === $filtros['resultado']));
        }

        return array_map(fn (Pdi $p) => [
            'pdi_id' => (int) $p->id,
            'codigo' => (string) $p->codigo,
            'colaborador_id' => $p->colaboradorId,
            'tipo' => $p->tipo->value,
            'estado' => $p->estado->value,
            'resultado' => $p->resultado?->value,
            'fecha_inicio' => $p->periodo->inicio->format('Y-m-d'),
            'fecha_fin' => $p->periodo->fin->format('Y-m-d'),
            'cerrado_at' => $p->cerradoAt?->format('Y-m-d\TH:i:sP'),
            'compromisos' => count($p->compromisos),
            'compromisos_cerrados' => count(array_filter($p->compromisos, fn (CompromisoDePdi $c) => $c->resultado === ResultadoDeCompromiso::Cerrada)),
        ], $planes);
    }

    /** Indica si el colaborador tiene un plan no Cerrado de ese tipo (Mejora, Carrera o Talento). */
    public function existePlanAbierto(int $tenantId, int $colaboradorId, string $tipo): bool
    {
        $tipoDePlan = TipoDePdi::tryFrom($tipo);

        return $tipoDePlan !== null && $this->repositorio->planAbierto($tenantId, $colaboradorId, $tipoDePlan) !== null;
    }

    private function hoy(): DateTimeImmutable
    {
        try {
            return $this->reloj->hoy(0);
        } catch (Throwable) {
            return new DateTimeImmutable('today');
        }
    }
}
