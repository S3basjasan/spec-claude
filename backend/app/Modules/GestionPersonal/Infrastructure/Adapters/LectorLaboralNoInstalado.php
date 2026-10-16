<?php

namespace App\Modules\GestionPersonal\Infrastructure\Adapters;

use App\Modules\GestionPersonal\Application\Ports\LectorLaboralPort;
use App\Modules\GestionPersonal\Domain\ValueObjects\Lectura;

/** GP-02 aún no existe: toda lectura laboral es «no instalado». */
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
}
