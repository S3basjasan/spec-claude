<?php

namespace App\Modules\Compensaciones\Infrastructure\Adapters;

use App\Modules\Compensaciones\Application\Ports\EquipoPort;

/**
 * PROVISIONAL, solo para el repositorio de prueba. Lee `config/compensaciones_prueba.php`:
 * `equipos` (líder => colaboradores) y `usuarios` (colaborador => usuario). Cuando Gestión de Personal publique
 * líder y equipo se escribe `EquipoDesdePersonal` y se cambia únicamente el enlace en `CompensacionesServiceProvider`.
 */
class EquipoDesdeConfiguracion implements EquipoPort
{
    public function esLider(int $colaboradorId): bool
    {
        return array_key_exists($colaboradorId, $this->equipos());
    }

    public function equipoDe(int $liderId): array
    {
        return array_values(array_map('intval', $this->equipos()[$liderId] ?? []));
    }

    public function liderDe(int $colaboradorId): ?int
    {
        foreach ($this->equipos() as $liderId => $equipo) {
            if (in_array($colaboradorId, $equipo, true)) {
                return (int) $liderId;
            }
        }

        return null;
    }

    public function usuariosDe(array $colaboradorIds): array
    {
        $usuarios = (array) config('compensaciones_prueba.usuarios', []);
        $resultado = [];

        foreach ($colaboradorIds as $id) {
            if (isset($usuarios[$id])) {
                $resultado[(int) $id] = (int) $usuarios[$id];
            }
        }

        return $resultado;
    }

    /** @return array<int, array<int, int>> */
    private function equipos(): array
    {
        return (array) config('compensaciones_prueba.equipos', []);
    }
}
