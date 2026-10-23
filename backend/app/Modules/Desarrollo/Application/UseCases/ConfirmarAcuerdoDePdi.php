<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\AprobadoresInterface;
use App\Modules\Desarrollo\Application\Services\ArmadorDeDetalleDePdi;
use App\Modules\Desarrollo\Domain\Exceptions\PdiIncompleto;
use App\Modules\Desarrollo\Domain\Exceptions\PdiNoEncontrado;
use App\Modules\Desarrollo\Domain\Exceptions\VersionDePdiDesfasada;
use App\Modules\Desarrollo\Domain\Repositories\RepositorioDePdiInterface;
use App\Modules\Desarrollo\Domain\Services\ReglasDeCompletitudDePdi;
use App\Modules\Desarrollo\Domain\Services\ReglasDeTransicionDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDeHistorialPdi;
use DateTimeImmutable;

/**
 * El responsable confirma el acuerdo de un Borrador completo. El plan sigue en Borrador y desde ese momento el colaborador
 * lo ve para aceptarlo o devolverlo (aclaración 1 de la spec).
 */
final class ConfirmarAcuerdoDePdi
{
    public function __construct(
        private readonly RepositorioDePdiInterface $repositorio,
        private readonly ReglasDeTransicionDePdi $reglas,
        private readonly ReglasDeCompletitudDePdi $completitud,
        private readonly AprobadoresInterface $aprobadores,
        private readonly ArmadorDeDetalleDePdi $armador,
    ) {}

    /** @return array<string, mixed> */
    public function ejecutar(int $tenantId, int $actorId, int $id, int $version): array
    {
        $pdi = $this->repositorio->obtener($tenantId, $id) ?? throw new PdiNoEncontrado;
        $this->reglas->exigirParaAcuerdo($pdi);
        if ($pdi->version !== $version) {
            throw new VersionDePdiDesfasada($pdi->version);
        }

        $faltantes = $this->completitud->faltantes($pdi, $this->aprobadores->esAdministradorActivo($tenantId, $pdi->responsableId));
        if ($faltantes !== []) {
            throw new PdiIncompleto($faltantes);
        }

        $ahora = new DateTimeImmutable;
        $nuevo = $pdi->con(['acuerdoResponsableAt' => $ahora, 'acuerdoResponsableBy' => $actorId, 'version' => $pdi->version + 1]);

        $this->repositorio->transaccion(function () use ($nuevo, $pdi, $actorId, $ahora) {
            $this->repositorio->actualizarCabecera($nuevo, $pdi->version, $actorId);
            $this->repositorio->registrarHistorial($pdi->tenantId, (int) $pdi->id, TipoDeHistorialPdi::Acuerdo, null, null, $ahora->format('Y-m-d H:i:s'), null, $actorId);
        });

        return $this->armador->detalle($this->repositorio->obtener($tenantId, $id), true, $actorId);
    }
}
