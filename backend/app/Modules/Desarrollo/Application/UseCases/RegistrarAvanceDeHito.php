<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Services\ArmadorDeDetalleDePdi;
use App\Modules\Desarrollo\Domain\Exceptions\PdiNoEncontrado;
use App\Modules\Desarrollo\Domain\Repositories\RepositorioDePdiInterface;
use App\Modules\Desarrollo\Domain\Services\ReglasDeTransicionDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDeHito;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDeHistorialPdi;
use DateTimeImmutable;

/** Marca un hito como Realizado (o lo vuelve a Pendiente) con observaciones en un plan Acordado. */
final class RegistrarAvanceDeHito
{
    public function __construct(
        private readonly RepositorioDePdiInterface $repositorio,
        private readonly ReglasDeTransicionDePdi $reglas,
        private readonly ArmadorDeDetalleDePdi $armador,
    ) {}

    /** @return array<string, mixed> el plan actualizado */
    public function ejecutar(int $tenantId, int $actorId, int $hitoId, EstadoDeHito $estado, ?string $observaciones): array
    {
        $encontrado = $this->repositorio->obtenerHito($tenantId, $hitoId) ?? throw new PdiNoEncontrado;
        $pdi = $this->repositorio->obtener($tenantId, $encontrado['pdi_id']) ?? throw new PdiNoEncontrado;
        $this->reglas->exigirParaSeguimiento($pdi);

        $hito = $encontrado['hito'];
        $observaciones = $observaciones === null || trim($observaciones) === '' ? null : trim($observaciones);

        $this->repositorio->transaccion(function () use ($hito, $estado, $observaciones, $pdi, $actorId, $hitoId) {
            $this->repositorio->actualizarHito($hitoId, $estado, $observaciones, $actorId, new DateTimeImmutable);
            $this->repositorio->registrarHistorial($pdi->tenantId, (int) $pdi->id, TipoDeHistorialPdi::Hito, $hitoId, $hito->estado->value, $estado->value, $observaciones, $actorId);
        });

        return $this->armador->detalle($this->repositorio->obtener($tenantId, (int) $pdi->id), true, $actorId);
    }
}
