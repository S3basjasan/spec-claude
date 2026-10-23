<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Services\ArmadorDeDetalleDePdi;
use App\Modules\Desarrollo\Domain\Exceptions\DatosInvalidosDePdi;
use App\Modules\Desarrollo\Domain\Exceptions\PdiNoEncontrado;
use App\Modules\Desarrollo\Domain\Exceptions\VersionDePdiDesfasada;
use App\Modules\Desarrollo\Domain\Repositories\RepositorioDePdiInterface;
use App\Modules\Desarrollo\Domain\Services\ReglasDeTransicionDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDeHistorialPdi;

/** El administrador devuelve un plan Acordado a Borrador con un motivo: se borran los acuerdos (FR-030). */
final class DevolverPdiABorrador
{
    public function __construct(
        private readonly RepositorioDePdiInterface $repositorio,
        private readonly ReglasDeTransicionDePdi $reglas,
        private readonly ArmadorDeDetalleDePdi $armador,
    ) {}

    /** @return array<string, mixed> */
    public function ejecutar(int $tenantId, int $actorId, int $id, string $motivo, int $version): array
    {
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw DatosInvalidosDePdi::campo('motivo', 'Indica el motivo para devolver el plan a Borrador.');
        }

        $pdi = $this->repositorio->obtener($tenantId, $id) ?? throw new PdiNoEncontrado;
        $this->reglas->exigirParaDevolverABorrador($pdi);
        if ($pdi->version !== $version) {
            throw new VersionDePdiDesfasada($pdi->version);
        }

        $nuevo = $pdi->con([
            'estado' => EstadoDePdi::Borrador,
            'acuerdoResponsableAt' => null,
            'acuerdoResponsableBy' => null,
            'aceptacionColaboradorAt' => null,
            'version' => $pdi->version + 1,
        ]);

        $this->repositorio->transaccion(function () use ($nuevo, $pdi, $actorId, $motivo) {
            $this->repositorio->actualizarCabecera($nuevo, $pdi->version, $actorId);
            $this->repositorio->registrarHistorial($pdi->tenantId, (int) $pdi->id, TipoDeHistorialPdi::Estado, null, EstadoDePdi::Acordado->value, EstadoDePdi::Borrador->value, null, $actorId);
            $this->repositorio->registrarHistorial($pdi->tenantId, (int) $pdi->id, TipoDeHistorialPdi::Devolucion, null, 'Acordado', 'Borrador', $motivo, $actorId);
        });

        return $this->armador->detalle($this->repositorio->obtener($tenantId, $id), true, $actorId);
    }
}
