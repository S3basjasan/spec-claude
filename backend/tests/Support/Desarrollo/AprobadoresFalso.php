<?php

namespace Tests\Support\Desarrollo;

use App\Modules\Desarrollo\Application\Ports\AprobadoresInterface;

/** Administradores falsos: `[id => nombre]`. */
class AprobadoresFalso implements AprobadoresInterface
{
    /** @param array<int, string> $administradores */
    public function __construct(public array $administradores = []) {}

    public function administradores(int $tenantId): array
    {
        return array_map(fn (int $id, string $n) => ['id' => $id, 'nombre' => $n], array_keys($this->administradores), array_values($this->administradores));
    }

    public function esAdministradorActivo(int $tenantId, int $usuarioId): bool
    {
        return isset($this->administradores[$usuarioId]);
    }
}
