<?php

namespace Tests\Support\Desarrollo;

use App\Modules\Desarrollo\Application\Ports\PlanDeFormacionInterface;
use App\Modules\Desarrollo\Domain\Exceptions\PlanNoDisponible;

/** Puerto falso del Plan de Formación: devuelve un id o simula que el módulo no está disponible. */
class PlanDeFormacionFalso implements PlanDeFormacionInterface
{
    /** @var list<array{tenant_id: int, actor_id: int, dnf_id: int, brechas: array<int, array<string, mixed>>}> */
    public array $solicitudes = [];

    public function __construct(protected readonly int $planId = 31, protected readonly bool $falla = false) {}

    public function crearBorrador(int $tenantId, int $actorId, int $dnfId, array $brechas): int
    {
        if ($this->falla) {
            throw new PlanNoDisponible;
        }
        $this->solicitudes[] = ['tenant_id' => $tenantId, 'actor_id' => $actorId, 'dnf_id' => $dnfId, 'brechas' => $brechas];

        return $this->planId;
    }
}
