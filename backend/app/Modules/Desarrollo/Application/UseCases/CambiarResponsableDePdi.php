<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\AprobadoresInterface;
use App\Modules\Desarrollo\Application\Ports\NombresDeUsuarioInterface;
use App\Modules\Desarrollo\Application\Services\ArmadorDeDetalleDePdi;
use App\Modules\Desarrollo\Domain\Exceptions\DatosInvalidosDePdi;
use App\Modules\Desarrollo\Domain\Exceptions\PdiNoEncontrado;
use App\Modules\Desarrollo\Domain\Exceptions\VersionDePdiDesfasada;
use App\Modules\Desarrollo\Domain\Repositories\RepositorioDePdiInterface;
use App\Modules\Desarrollo\Domain\Services\ReglasDeTransicionDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDeHistorialPdi;
use Throwable;

/**
 * Cambia el responsable de un plan en Borrador o Acordado (nunca Cerrado). En un plan Acordado se conservan los acuerdos
 * y el estado; en Borrador cuenta como edición y los borra (aclaración 2 de la spec, FR-006, FR-028).
 */
final class CambiarResponsableDePdi
{
    public function __construct(
        private readonly RepositorioDePdiInterface $repositorio,
        private readonly ReglasDeTransicionDePdi $reglas,
        private readonly AprobadoresInterface $aprobadores,
        private readonly NombresDeUsuarioInterface $usuarios,
        private readonly ArmadorDeDetalleDePdi $armador,
    ) {}

    /** @return array<string, mixed> */
    public function ejecutar(int $tenantId, int $actorId, int $id, int $responsableId, int $version): array
    {
        $pdi = $this->repositorio->obtener($tenantId, $id) ?? throw new PdiNoEncontrado;
        $this->reglas->exigirParaCambiarResponsable($pdi);
        if ($pdi->version !== $version) {
            throw new VersionDePdiDesfasada($pdi->version);
        }
        if (! $this->aprobadores->esAdministradorActivo($tenantId, $responsableId)) {
            throw DatosInvalidosDePdi::campo('responsable_id', 'El responsable debe ser un administrador activo.');
        }

        $cambios = ['responsableId' => $responsableId, 'version' => $pdi->version + 1];
        if ($this->reglas->borraAcuerdosAlEditar($pdi)) {
            $cambios += ['acuerdoResponsableAt' => null, 'acuerdoResponsableBy' => null, 'aceptacionColaboradorAt' => null];
        }
        $nuevo = $pdi->con($cambios);

        $nombres = $this->nombres([$pdi->responsableId, $responsableId]);
        $this->repositorio->transaccion(function () use ($nuevo, $pdi, $actorId, $nombres, $responsableId) {
            $this->repositorio->actualizarCabecera($nuevo, $pdi->version, $actorId);
            $this->repositorio->registrarHistorial(
                $pdi->tenantId, (int) $pdi->id, TipoDeHistorialPdi::Responsable, null,
                $nombres[$pdi->responsableId] ?? '#'.$pdi->responsableId, $nombres[$responsableId] ?? '#'.$responsableId, null, $actorId,
            );
        });

        return $this->armador->detalle($this->repositorio->obtener($tenantId, $id), true, $actorId);
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function nombres(array $ids): array
    {
        try {
            return $this->usuarios->nombresDe($ids);
        } catch (Throwable) {
            return [];
        }
    }
}
