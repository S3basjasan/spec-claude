<?php

namespace App\Modules\Desarrollo\Application\Services;

use App\Modules\Desarrollo\Application\Ports\AprobadoresInterface;
use App\Modules\Desarrollo\Application\Ports\DepartamentoDeColaboradorInterface;
use App\Modules\Desarrollo\Application\Ports\DepartamentosInterface;
use App\Modules\Desarrollo\Application\Ports\NombresDeReferenciaInterface;
use App\Modules\Desarrollo\Application\Ports\NombresDeUsuarioInterface;
use App\Modules\Desarrollo\Application\Ports\RelojInterface;
use App\Modules\Desarrollo\Domain\Entities\Pdi;
use App\Modules\Desarrollo\Domain\Services\CalculadorDeAvance;
use App\Modules\Desarrollo\Domain\Services\HitosVencidos;
use Throwable;

/** Filas de resumen de planes (lista, Gantt): nombres y departamentos resueltos en lote por página. */
final class ArmadorDeResumenesDePdi
{
    private const NO_DISPONIBLE = 'No disponible';

    public function __construct(
        private readonly NombresDeReferenciaInterface $nombres,
        private readonly NombresDeUsuarioInterface $usuarios,
        private readonly DepartamentoDeColaboradorInterface $departamentoDe,
        private readonly DepartamentosInterface $departamentos,
        private readonly AprobadoresInterface $aprobadores,
        private readonly ResolvedorDeEstadoDeAcciones $resolvedor,
        private readonly CalculadorDeAvance $avance,
        private readonly HitosVencidos $vencidos,
        private readonly RelojInterface $reloj,
    ) {}

    /**
     * @param  list<Pdi>  $planes  del mismo tenant
     * @return list<array<string, mixed>>
     */
    public function resumenes(array $planes, int $tenantId, int $usuarioId): array
    {
        if ($planes === []) {
            return [];
        }

        $hoy = $this->reloj->hoy($usuarioId);
        $colaboradorIds = array_values(array_unique(array_map(fn (Pdi $p) => $p->colaboradorId, $planes)));
        $usuarioIds = array_values(array_unique(array_map(fn (Pdi $p) => $p->responsableId, $planes)));
        $puestoIds = array_values(array_unique(array_filter(array_map(fn (Pdi $p) => $p->puestoObjetivoId, $planes))));

        $colaboradores = $this->seguro(fn () => $this->nombres->colaboradores($tenantId, $colaboradorIds), []);
        $responsables = $this->seguro(fn () => $this->usuarios->nombresDe($usuarioIds), []);
        $puestos = $puestoIds === [] ? [] : $this->seguro(fn () => $this->nombres->puestos($puestoIds), []);
        $departamentos = $this->departamentosDe($tenantId, $colaboradorIds, $hoy->format('Y-m-d'));

        return array_map(function (Pdi $p) use ($hoy, $colaboradores, $responsables, $puestos, $departamentos, $tenantId) {
            $estados = array_map(fn (array $e) => $e['estado'], array_values($this->resolvedor->resolver($p, $hoy)));

            return [
                'id' => $p->id,
                'codigo' => $p->codigo,
                'colaborador' => [
                    'id' => $p->colaboradorId,
                    'nombre' => $colaboradores[$p->colaboradorId] ?? self::NO_DISPONIBLE,
                    'departamento' => $departamentos[$p->colaboradorId] ?? null,
                ],
                'tipo' => $p->tipo->value,
                'estado' => $p->estado->value,
                'objetivo' => $p->objetivo,
                'puesto_objetivo' => $p->puestoObjetivoId === null ? null : [
                    'id' => $p->puestoObjetivoId,
                    'nombre' => $puestos[$p->puestoObjetivoId]['nombre'] ?? self::NO_DISPONIBLE,
                ],
                'responsable' => [
                    'id' => $p->responsableId,
                    'nombre' => $responsables[$p->responsableId] ?? self::NO_DISPONIBLE,
                    'sin_permiso' => ! $this->seguro(fn () => $this->aprobadores->esAdministradorActivo($tenantId, $p->responsableId), true),
                ],
                'fecha_inicio' => $p->periodo->inicio->format('Y-m-d'),
                'fecha_fin' => $p->periodo->fin->format('Y-m-d'),
                'avance' => $this->avance->calcular($estados),
                'hitos_vencidos' => $p->estado->value === 'Cerrado' ? 0 : $this->vencidos->contar($p->hitos, $hoy),
                'resultado' => $p->resultado?->value,
                'version' => $p->version,
            ];
        }, $planes);
    }

    /**
     * @param  list<int>  $colaboradorIds
     * @return array<int, string|null>
     */
    private function departamentosDe(int $tenantId, array $colaboradorIds, string $hoy): array
    {
        return $this->seguro(function () use ($tenantId, $colaboradorIds, $hoy) {
            $unidades = $this->departamentoDe->actuales($tenantId, $colaboradorIds, $hoy);
            $nombres = $this->departamentos->porIds(array_values(array_unique(array_filter($unidades))));

            return array_map(fn ($unidad) => $unidad === null ? null : ($nombres[$unidad] ?? null), $unidades);
        }, []);
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
