<?php

namespace Tests\Support\Compensaciones;

use App\Modules\Compensaciones\Application\Ports\ColaboradoresPort;

/** Colaboradores en memoria: ninguna prueba de Compensaciones lee Gestión de Personal. */
final class ColaboradoresFalso implements ColaboradoresPort
{
    /** @var array<int, int> usuario_id => colaborador_id */
    public array $cuentas = [];

    /** @var array<int, int|null> colaborador_id => unidad_id */
    public array $unidades = [];

    public function cuentaDeUsuario(int $usuarioId): ?int
    {
        return $this->cuentas[$usuarioId] ?? null;
    }

    public function nombres(array $ids): array
    {
        $resultado = [];

        foreach ($ids as $id) {
            $resultado[$id] = ['id' => $id, 'codigo' => 'COL-'.$id, 'nombre' => 'Colaborador '.$id];
        }

        return $resultado;
    }

    public function vigentes(array $ids, string $fecha): array
    {
        return array_values($ids);
    }

    public function unidadActual(array $ids, string $fecha): array
    {
        $resultado = [];

        foreach ($ids as $id) {
            $resultado[$id] = $this->unidades[$id] ?? null;
        }

        return $resultado;
    }
}
