<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\DTO\ListadoMicroDTO;
use App\Modules\Desarrollo\Application\Ports\CuentaColaboradorInterface;
use App\Modules\Desarrollo\Application\Services\ArmadorDeMicro;
use App\Modules\Desarrollo\Domain\Repositories\MicrocontenidoRepositoryInterface;
use App\Modules\Desarrollo\Domain\Repositories\ProgresoMicroRepositoryInterface;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoMicro;
use App\Modules\Desarrollo\Domain\ValueObjects\FiltroMicro;

/** Repositorio de micro-contenidos: el Administrador ve todos los estados; el colaborador, solo los Publicados con su progreso. */
final class ListarMicrocontenidos
{
    public function __construct(
        private readonly MicrocontenidoRepositoryInterface $repositorio,
        private readonly ProgresoMicroRepositoryInterface $progresos,
        private readonly CuentaColaboradorInterface $cuentas,
        private readonly ArmadorDeMicro $armador,
    ) {}

    public function ejecutar(FiltroMicro $filtro, bool $esAdmin, int $usuarioId): ListadoMicroDTO
    {
        if (! $esAdmin) {
            $filtro = new FiltroMicro($filtro->tipo, [EstadoMicro::Publicado], $filtro->competenciaId, $filtro->q, $filtro->pagina, $filtro->porPagina);
        }

        $resultado = $this->repositorio->listar($filtro);

        $progresos = [];
        if (! $esAdmin) {
            $cuenta = $this->cuentas->cuentaDe($usuarioId);
            if ($cuenta !== null) {
                foreach ($this->progresos->progresosDe($cuenta['colaborador_id']) as $p) {
                    $progresos[$p['progreso']->microcontenidoId] = $this->armador->progresoComoArreglo($p['progreso'], $p['ultimo_puntaje']);
                }
            }
        }

        return new ListadoMicroDTO(
            $this->armador->armar($resultado['items'], $esAdmin, false, $progresos),
            $resultado['total'],
            $filtro->pagina,
            $filtro->porPagina,
        );
    }
}
