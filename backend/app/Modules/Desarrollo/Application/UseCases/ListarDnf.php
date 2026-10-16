<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\DTO\FilaHistorialDnfDTO;
use App\Modules\Desarrollo\Application\DTO\PaginaHistorialDnfDTO;
use App\Modules\Desarrollo\Application\Ports\NombresDeReferenciaInterface;
use App\Modules\Desarrollo\Application\Services\ArmadorDeDnf;
use App\Modules\Desarrollo\Domain\Repositories\DnfRepositoryInterface;
use App\Modules\Desarrollo\Domain\ValueObjects\FiltroDnf;
use App\Modules\Desarrollo\Domain\ValueObjects\ResumenDnf;
use Throwable;

/**
 * Historial de DNF del tenant: código, fecha, alcance, colaborador o departamento, cantidad de brechas y prioridad más
 * alta. No incluye el detalle de brechas. Los nombres se resuelven por lote.
 */
final class ListarDnf
{
    public function __construct(
        private readonly DnfRepositoryInterface $repositorio,
        private readonly NombresDeReferenciaInterface $nombres,
    ) {}

    public function ejecutar(int $tenantId, FiltroDnf $filtro): PaginaHistorialDnfDTO
    {
        $pagina = $this->repositorio->listar($tenantId, $filtro);

        $colaboradores = $this->seguro(fn () => $this->nombres->colaboradores(
            $tenantId,
            array_values(array_unique(array_filter(array_map(fn (ResumenDnf $r) => $r->colaboradorId, $pagina->items)))),
        ));
        $unidades = $this->seguro(fn () => $this->nombres->unidades(
            array_values(array_unique(array_filter(array_map(fn (ResumenDnf $r) => $r->departamentoId, $pagina->items)))),
        ));

        $filas = array_map(fn (ResumenDnf $r) => new FilaHistorialDnfDTO(
            $r->id,
            $r->codigo,
            $r->fecha->format(DATE_ATOM),
            $r->alcance->value,
            $r->colaboradorId === null ? null : ['id' => $r->colaboradorId, 'nombre' => $colaboradores[$r->colaboradorId] ?? ArmadorDeDnf::NO_DISPONIBLE],
            $r->departamentoId === null ? null : ['id' => $r->departamentoId, 'nombre' => $unidades[$r->departamentoId] ?? ArmadorDeDnf::NO_DISPONIBLE],
            $r->cantidadBrechas,
            $r->prioridadMasAlta?->value,
            $r->tienePlan,
        ), $pagina->items);

        return new PaginaHistorialDnfDTO($filas, $pagina->total, $pagina->pagina, $pagina->porPagina, $pagina->ultimaPagina());
    }

    /**
     * @return array<int, string>
     */
    private function seguro(callable $consulta): array
    {
        try {
            return $consulta();
        } catch (Throwable) {
            return [];
        }
    }
}
