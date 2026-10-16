<?php

namespace App\Modules\GestionPersonal\Infrastructure\Adapters;

use App\Modules\GestionPersonal\Application\Ports\LectorLaboralPort;
use App\Modules\GestionPersonal\Domain\ValueObjects\Lectura;

/** Lectura laboral «no instalado»: útil en pruebas y entornos sin vínculos. */
final class LectorLaboralNoInstalado implements LectorLaboralPort
{
    public function situacionVigente(int $tenantId, array $colaboradorIds, string $fecha): Lectura
    {
        return Lectura::noInstalado();
    }

    public function idsConVinculoVigente(int $tenantId, string $fecha): Lectura
    {
        return Lectura::noInstalado();
    }

    public function idsPorEstado(int $tenantId, string $estado, string $fecha): Lectura
    {
        return Lectura::noInstalado();
    }

    public function colaboradoresEnPosiciones(int $tenantId, array $posicionIds, string $fecha): Lectura
    {
        return Lectura::noInstalado();
    }

    public function trayectoria(int $tenantId, int $colaboradorId): Lectura
    {
        return Lectura::noInstalado();
    }

    public function estadosPresentes(int $tenantId, string $fecha): Lectura
    {
        return Lectura::noInstalado();
    }

    public function proximoIngreso(int $tenantId, int $colaboradorId, string $fecha): Lectura
    {
        return Lectura::noInstalado();
    }
}
