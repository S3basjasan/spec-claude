<?php

namespace App\Modules\Desarrollo\Application\Services;

use App\Modules\Desarrollo\Application\DTO\BrechaDTO;
use App\Modules\Desarrollo\Application\DTO\DnfDTO;
use App\Modules\Desarrollo\Application\DTO\ResultadoColaboradorDTO;
use App\Modules\Desarrollo\Application\Ports\NombresDeReferenciaInterface;
use App\Modules\Desarrollo\Application\Ports\NombresDeUsuarioInterface;
use App\Modules\Desarrollo\Domain\Entities\Brecha;
use App\Modules\Desarrollo\Domain\Entities\Dnf;
use App\Modules\Desarrollo\Domain\Entities\ResultadoColaborador;
use App\Modules\Desarrollo\Domain\ValueObjects\AlcanceDnf;
use Throwable;

/**
 * Convierte una DNF del dominio en un DTO de presentación resolviendo, por lote, los nombres de colaboradores,
 * puestos, competencias, departamento y usuarios. Lo que no se pueda resolver se muestra como «No disponible».
 */
final class ArmadorDeDnf
{
    public const NO_DISPONIBLE = 'No disponible';

    public function __construct(
        private readonly NombresDeReferenciaInterface $nombres,
        private readonly NombresDeUsuarioInterface $usuarios,
    ) {}

    /**
     * @param  bool  $conBrechas  incluye el detalle de brechas de cada colaborador (siempre en una individual)
     */
    public function armar(Dnf $dnf, bool $conBrechas): DnfDTO
    {
        $incluirBrechas = $conBrechas || $dnf->alcance === AlcanceDnf::Individual;
        $referencias = $this->referencias($dnf);
        $colaboradores = array_map(
            fn (ResultadoColaborador $r) => $this->armarResultado($r, $referencias, $incluirBrechas),
            $dnf->resultados,
        );

        return new DnfDTO(
            (int) $dnf->id,
            (string) $dnf->codigo,
            $dnf->alcance->value,
            $dnf->creadoEn?->format(DATE_ATOM) ?? '',
            $dnf->departamentoId === null ? null : [
                'id' => $dnf->departamentoId,
                'nombre' => $referencias['unidades'][$dnf->departamentoId] ?? self::NO_DISPONIBLE,
            ],
            $this->usuario($dnf->creadoPor, $referencias['usuarios']),
            $dnf->competenciasOmitidas,
            $dnf->planFormacionId === null ? null : [
                'id' => $dnf->planFormacionId,
                'generado_por' => $dnf->planGeneradoPor === null ? null : $this->usuario($dnf->planGeneradoPor, $referencias['usuarios']),
                'generado_en' => $dnf->planGeneradoEn?->format(DATE_ATOM),
            ],
            count($dnf->resultados),
            $dnf->cantidadBrechas(),
            $dnf->prioridadMasAlta()?->value,
            $dnf->sinEvaluacion(),
            $colaboradores,
        );
    }

    /**
     * Resuelve los nombres de una lista de brechas (usado por «Mi diagnóstico»).
     *
     * @param  list<Brecha>  $brechas
     * @return list<BrechaDTO>
     */
    public function armarBrechas(array $brechas): array
    {
        $competencias = $this->seguro(fn () => $this->nombres->competencias(array_values(array_unique(array_map(fn (Brecha $b) => $b->competenciaId, $brechas)))), []);

        return array_map(fn (Brecha $b) => BrechaDTO::desde($b, $competencias[$b->competenciaId] ?? null, self::NO_DISPONIBLE), $brechas);
    }

    /**
     * @param  array{colaboradores: array<int, string>, puestos: array<int, array{codigo: string, nombre: string}>, competencias: array<int, array{codigo: string, nombre: string, activa: bool}>, unidades: array<int, string>, usuarios: array<int, string>}  $referencias
     */
    private function armarResultado(ResultadoColaborador $resultado, array $referencias, bool $incluirBrechas): ResultadoColaboradorDTO
    {
        $puesto = $referencias['puestos'][$resultado->puestoId] ?? null;
        $brechas = null;
        if ($incluirBrechas) {
            $ordenadas = $resultado->brechas;
            usort($ordenadas, fn (Brecha $a, Brecha $b) => $this->comparar($a, $b, $referencias['competencias']));
            $brechas = array_map(
                fn (Brecha $b) => BrechaDTO::desde($b, $referencias['competencias'][$b->competenciaId] ?? null, self::NO_DISPONIBLE),
                $ordenadas,
            );
        }

        return new ResultadoColaboradorDTO(
            $resultado->colaboradorId,
            $referencias['colaboradores'][$resultado->colaboradorId] ?? self::NO_DISPONIBLE,
            [
                'id' => $resultado->puestoId,
                'codigo' => $puesto['codigo'] ?? sprintf('PU-%03d', $resultado->puestoId),
                'nombre' => $puesto['nombre'] ?? self::NO_DISPONIBLE,
            ],
            $resultado->cantidadBrechas(),
            $resultado->prioridadMasAlta()?->value,
            $resultado->sinEvaluacion(),
            $brechas,
        );
    }

    /**
     * Prioridad (mayor primero), luego mayor diferencia en grados, luego nombre de competencia.
     *
     * @param  array<int, array{codigo: string, nombre: string, activa: bool}>  $competencias
     */
    private function comparar(Brecha $a, Brecha $b, array $competencias): int
    {
        return [$b->prioridad?->orden() ?? 0, $a->diferencia ?? PHP_INT_MAX, $competencias[$a->competenciaId]['nombre'] ?? '']
            <=> [$a->prioridad?->orden() ?? 0, $b->diferencia ?? PHP_INT_MAX, $competencias[$b->competenciaId]['nombre'] ?? ''];
    }

    /**
     * @return array{colaboradores: array<int, string>, puestos: array<int, array{codigo: string, nombre: string}>, competencias: array<int, array{codigo: string, nombre: string, activa: bool}>, unidades: array<int, string>, usuarios: array<int, string>}
     */
    private function referencias(Dnf $dnf): array
    {
        $colaboradorIds = array_map(fn (ResultadoColaborador $r) => $r->colaboradorId, $dnf->resultados);
        $puestoIds = array_values(array_unique(array_map(fn (ResultadoColaborador $r) => $r->puestoId, $dnf->resultados)));
        $competenciaIds = [];
        foreach ($dnf->resultados as $resultado) {
            foreach ($resultado->brechas as $brecha) {
                $competenciaIds[$brecha->competenciaId] = true;
            }
        }
        $usuarioIds = array_values(array_filter([$dnf->creadoPor, $dnf->planGeneradoPor]));

        return [
            'colaboradores' => $this->seguro(fn () => $this->nombres->colaboradores($dnf->tenantId, $colaboradorIds), []),
            'puestos' => $this->seguro(fn () => $this->nombres->puestos($puestoIds), []),
            'competencias' => $this->seguro(fn () => $this->nombres->competencias(array_keys($competenciaIds)), []),
            'unidades' => $dnf->departamentoId === null ? [] : $this->seguro(fn () => $this->nombres->unidades([$dnf->departamentoId]), []),
            'usuarios' => $this->seguro(fn () => $this->usuarios->nombresDe($usuarioIds), []),
        ];
    }

    /**
     * @param  array<int, string>  $usuarios
     * @return array{id: int, nombre: string}
     */
    private function usuario(int $id, array $usuarios): array
    {
        return ['id' => $id, 'nombre' => $usuarios[$id] ?? self::NO_DISPONIBLE];
    }

    /**
     * @template T
     *
     * @param  callable(): T  $consulta
     * @param  T  $porDefecto
     * @return T
     */
    private function seguro(callable $consulta, mixed $porDefecto): mixed
    {
        try {
            return $consulta();
        } catch (Throwable) {
            return $porDefecto;
        }
    }
}
