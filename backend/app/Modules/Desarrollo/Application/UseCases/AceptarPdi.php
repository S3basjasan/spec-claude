<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Services\AccesoAPdi;
use App\Modules\Desarrollo\Application\Services\ArmadorDeDetalleDePdi;
use App\Modules\Desarrollo\Domain\Exceptions\VersionDePdiDesfasada;
use App\Modules\Desarrollo\Domain\Repositories\RepositorioDePdiInterface;
use App\Modules\Desarrollo\Domain\Services\ReglasDeTransicionDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDeHistorialPdi;
use DateTimeImmutable;

/** El colaborador acepta su plan en Borrador con el acuerdo del responsable vigente; el plan pasa a Acordado (FR-027). */
final class AceptarPdi
{
    public function __construct(
        private readonly AccesoAPdi $acceso,
        private readonly RepositorioDePdiInterface $repositorio,
        private readonly ReglasDeTransicionDePdi $reglas,
        private readonly ArmadorDeDetalleDePdi $armador,
    ) {}

    /** @return array<string, mixed> */
    public function ejecutar(int $tenantId, int $usuarioId, int $id, int $version): array
    {
        $pdi = $this->acceso->cargarParaResponder($tenantId, $usuarioId, $id);
        $this->reglas->exigirParaRespuestaDelColaborador($pdi);
        if ($pdi->version !== $version) {
            throw new VersionDePdiDesfasada($pdi->version);
        }

        $ahora = new DateTimeImmutable;
        $nuevo = $pdi->con(['aceptacionColaboradorAt' => $ahora, 'estado' => EstadoDePdi::Acordado, 'version' => $pdi->version + 1]);

        $this->repositorio->transaccion(function () use ($nuevo, $pdi, $usuarioId, $ahora) {
            $this->repositorio->actualizarCabecera($nuevo, $pdi->version, $usuarioId);
            $this->repositorio->registrarHistorial($pdi->tenantId, (int) $pdi->id, TipoDeHistorialPdi::Aceptacion, null, null, $ahora->format('Y-m-d H:i:s'), null, $usuarioId);
            $this->repositorio->registrarHistorial($pdi->tenantId, (int) $pdi->id, TipoDeHistorialPdi::Estado, null, EstadoDePdi::Borrador->value, EstadoDePdi::Acordado->value, null, $usuarioId);
        });

        return $this->armador->detalle($this->repositorio->obtener($tenantId, $id), false, $usuarioId);
    }
}
