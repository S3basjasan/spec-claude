<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\RelojInterface;
use App\Modules\Desarrollo\Application\Ports\ValidadorDeVinculoInterface;
use App\Modules\Desarrollo\Application\Services\ArmadorDeDetalleDePdi;
use App\Modules\Desarrollo\Application\Services\ResolvedorDeEstadoDeAcciones;
use App\Modules\Desarrollo\Domain\Exceptions\DatosInvalidosDePdi;
use App\Modules\Desarrollo\Domain\Exceptions\PdiNoEncontrado;
use App\Modules\Desarrollo\Domain\Exceptions\VersionDePdiDesfasada;
use App\Modules\Desarrollo\Domain\Repositories\RepositorioDePdiInterface;
use App\Modules\Desarrollo\Domain\Services\ReglasDeTransicionDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDeAccionPdi;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDeHistorialPdi;

/**
 * Vincula (o desvincula) una acción del PDI con una acción del Plan de Formación. Al vincular, el estado manual se
 * descarta y pasa a derivarse; al desvincular, queda como estado manual el último que mostraba. En un Borrador cuenta como
 * edición (borra los acuerdos); en un Acordado no altera acuerdos ni estado (FR-018 a FR-023). El Plan de Formación no se
 * modifica.
 */
final class VincularAccionDePdi
{
    public function __construct(
        private readonly RepositorioDePdiInterface $repositorio,
        private readonly ReglasDeTransicionDePdi $reglas,
        private readonly ValidadorDeVinculoInterface $validador,
        private readonly ResolvedorDeEstadoDeAcciones $resolvedor,
        private readonly ArmadorDeDetalleDePdi $armador,
        private readonly RelojInterface $reloj,
    ) {}

    /** @return array<string, mixed> el plan actualizado */
    public function ejecutar(int $tenantId, int $actorId, int $accionId, ?int $planAccionId, ?int $version): array
    {
        $encontrada = $this->repositorio->obtenerAccion($tenantId, $accionId) ?? throw new PdiNoEncontrado;
        $pdi = $this->repositorio->obtener($tenantId, $encontrada['pdi_id']) ?? throw new PdiNoEncontrado;
        $this->reglas->exigirParaVincular($pdi);
        $accion = $encontrada['accion'];

        $editaBorrador = $this->reglas->borraAcuerdosAlEditar($pdi);
        if ($editaBorrador) {
            if ($version === null) {
                throw DatosInvalidosDePdi::campo('version', 'Falta la versión del plan.');
            }
            if ($version !== $pdi->version) {
                throw new VersionDePdiDesfasada($pdi->version);
            }
        }

        if ($planAccionId !== null) {
            $this->validador->validar($tenantId, $pdi->colaboradorId, $accion->tipo, $planAccionId);
        }

        if ($planAccionId === $accion->planAccionId) {
            return $this->armador->detalle($pdi, true, $actorId);
        }

        $estadoNuevo = EstadoDeAccionPdi::Pendiente;
        if ($planAccionId === null) {
            $resuelto = $this->resolvedor->resolver($pdi, $this->reloj->hoy($actorId))[$accionId]['estado'] ?? $accion->estado;
            $estadoNuevo = $resuelto;
        }

        $this->repositorio->transaccion(function () use ($pdi, $accion, $accionId, $planAccionId, $estadoNuevo, $actorId, $editaBorrador) {
            if ($editaBorrador) {
                $this->repositorio->actualizarCabecera($pdi->con([
                    'acuerdoResponsableAt' => null,
                    'acuerdoResponsableBy' => null,
                    'aceptacionColaboradorAt' => null,
                    'version' => $pdi->version + 1,
                ]), $pdi->version, $actorId);
            }
            $this->repositorio->actualizarAccion($accionId, $estadoNuevo, false, $planAccionId, $actorId);
            $this->repositorio->registrarHistorial(
                $pdi->tenantId, (int) $pdi->id, TipoDeHistorialPdi::Vinculo, $accionId,
                $accion->planAccionId === null ? null : '#'.$accion->planAccionId,
                $planAccionId === null ? null : '#'.$planAccionId,
                $accion->descripcion, $actorId,
            );
        });

        return $this->armador->detalle($this->repositorio->obtener($tenantId, (int) $pdi->id), true, $actorId);
    }
}
