<?php

namespace Tests\Support\Compensaciones;

use App\Modules\Compensaciones\Application\Ports\EquipoPort;

final class EquipoFalso implements EquipoPort
{
    /** @var array<int, array<int, int>> líder => colaboradores */
    public array $equipos = [];

    /** @var array<int, int> colaborador => usuario */
    public array $usuarios = [];

    public function esLider(int $colaboradorId): bool
    {
        return array_key_exists($colaboradorId, $this->equipos);
    }

    public function equipoDe(int $liderId): array
    {
        return $this->equipos[$liderId] ?? [];
    }

    public function liderDe(int $colaboradorId): ?int
    {
        foreach ($this->equipos as $lider => $equipo) {
            if (in_array($colaboradorId, $equipo, true)) {
                return $lider;
            }
        }

        return null;
    }

    public function usuariosDe(array $colaboradorIds): array
    {
        return array_intersect_key($this->usuarios, array_flip($colaboradorIds));
    }
}
