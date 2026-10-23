<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\BrechasParaPdiInterface;
use App\Modules\Desarrollo\Application\Ports\ColaboradoresVigentesInterface;
use App\Modules\Desarrollo\Application\Ports\DepartamentoDeColaboradorInterface;
use App\Modules\Desarrollo\Application\Ports\DepartamentosInterface;
use App\Modules\Desarrollo\Application\Ports\NombresDeReferenciaInterface;
use App\Modules\Desarrollo\Application\Ports\RelojInterface;
use App\Modules\Desarrollo\Domain\Repositories\RepositorioDePdiInterface;
use Throwable;

/**
 * Colaboradores con brecha activa y sin plan no Cerrado (research R6): brecha de la última DNF sin plan de Mejora abierto,
 * o adecuación vigente con brechas sin plan de Carrera ni de Talento abierto para ese puesto objetivo. Se evalúan hasta
 * 200 colaboradores vigentes por consulta.
 */
final class ListarColaboradoresSinPlan
{
    public const MAXIMO_EVALUADOS = 200;

    public function __construct(
        private readonly ColaboradoresVigentesInterface $colaboradores,
        private readonly DepartamentoDeColaboradorInterface $departamentoDe,
        private readonly DepartamentosInterface $departamentos,
        private readonly BrechasParaPdiInterface $brechas,
        private readonly ConsultarAdecuacionesParaOtrosModulos $adecuaciones,
        private readonly NombresDeReferenciaInterface $nombres,
        private readonly RepositorioDePdiInterface $repositorio,
        private readonly RelojInterface $reloj,
    ) {}

    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function ejecutar(int $tenantId, int $usuarioId, ?int $departamentoId, int $pagina, int $porPagina): array
    {
        $hoy = $this->reloj->hoy($usuarioId)->format('Y-m-d');
        $vigentes = $this->colaboradores->buscar($tenantId, null, 1, self::MAXIMO_EVALUADOS, $hoy)['items'];
        $ids = array_map(fn (array $c) => (int) $c['id'], $vigentes);
        $unidades = $this->unidades($tenantId, $ids, $hoy);

        if ($departamentoId !== null) {
            $vigentes = array_values(array_filter($vigentes, fn (array $c) => ($unidades[(int) $c['id']] ?? null) === $departamentoId));
            $ids = array_map(fn (array $c) => (int) $c['id'], $vigentes);
        }

        $abiertos = [];
        foreach ($this->repositorio->abiertosDeColaboradores($tenantId, $ids) as $a) {
            $abiertos[$a['colaborador_id']][] = $a;
        }
        $adecuaciones = [];
        foreach ($this->adecuaciones->adecuacionesPorFiltros($tenantId) as $a) {
            if ($a['brechas'] > 0) {
                $adecuaciones[$a['colaborador_id']][] = $a;
            }
        }

        $filas = [];
        foreach ($vigentes as $c) {
            $id = (int) $c['id'];
            $planes = $abiertos[$id] ?? [];
            $tieneMejora = count(array_filter($planes, fn (array $p) => $p['tipo'] === 'Mejora')) > 0;

            $dnf = $tieneMejora ? null : $this->brechas->ultimaDnf($tenantId, $id);
            if ($dnf !== null && $dnf['brechas'] !== []) {
                $filas[] = ['colaborador_id' => $id, 'nombre' => (string) $c['nombre'], 'origen' => 'dnf', 'tipo_sugerido' => 'Mejora', 'puesto_objetivo_id' => null, 'brechas' => count($dnf['brechas'])];
            }

            foreach ($adecuaciones[$id] ?? [] as $a) {
                $cubierta = count(array_filter($planes, fn (array $p) => in_array($p['tipo'], ['Carrera', 'Talento'], true) && $p['puesto_objetivo_id'] === $a['puesto_objetivo_id'])) > 0;
                if (! $cubierta) {
                    $filas[] = ['colaborador_id' => $id, 'nombre' => (string) $c['nombre'], 'origen' => 'adecuacion', 'tipo_sugerido' => 'Carrera', 'puesto_objetivo_id' => $a['puesto_objetivo_id'], 'brechas' => $a['brechas']];
                }
            }
        }

        $total = count($filas);
        $pagina = max(1, $pagina);
        $slice = array_slice($filas, ($pagina - 1) * $porPagina, $porPagina);
        $puestos = $this->puestos(array_values(array_unique(array_filter(array_column($slice, 'puesto_objetivo_id')))));
        $nombresDeUnidad = $this->seguro(fn () => $this->departamentos->porIds(array_values(array_unique(array_filter($unidades)))), []);

        return [
            'data' => array_map(fn (array $f) => [
                'colaborador' => [
                    'id' => $f['colaborador_id'],
                    'nombre' => $f['nombre'],
                    'departamento' => isset($unidades[$f['colaborador_id']]) ? ($nombresDeUnidad[$unidades[$f['colaborador_id']]] ?? null) : null,
                ],
                'origen' => $f['origen'],
                'tipo_sugerido' => $f['tipo_sugerido'],
                'puesto_objetivo' => $f['puesto_objetivo_id'] === null ? null : ['id' => $f['puesto_objetivo_id'], 'nombre' => $puestos[$f['puesto_objetivo_id']]['nombre'] ?? 'No disponible'],
                'brechas' => $f['brechas'],
            ], $slice),
            'meta' => ['page' => $pagina, 'per_page' => $porPagina, 'total' => $total, 'last_page' => max(1, (int) ceil($total / $porPagina))],
        ];
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, int|null>
     */
    private function unidades(int $tenantId, array $ids, string $hoy): array
    {
        return $ids === [] ? [] : $this->seguro(fn () => $this->departamentoDe->actuales($tenantId, $ids, $hoy), []);
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{codigo: string, nombre: string}>
     */
    private function puestos(array $ids): array
    {
        return $ids === [] ? [] : $this->seguro(fn () => $this->nombres->puestos($ids), []);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $fuente
     * @param  T  $porDefecto
     * @return T
     */
    private function seguro(callable $fuente, mixed $porDefecto): mixed
    {
        try {
            return $fuente();
        } catch (Throwable) {
            return $porDefecto;
        }
    }
}
