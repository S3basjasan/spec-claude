<?php

namespace Tests\Support\Compensaciones;

use App\Modules\Compensaciones\Application\Ports\UnidadesPort;

/** Unidades en memoria: ninguna prueba de Compensaciones lee Estructura. */
final class UnidadesFalso implements UnidadesPort
{
    /** @var array<int, array{id: int, codigo: string, nombre: string, estado: string}> */
    public array $unidades = [];

    public function agregar(int $id, string $nombre, string $estado = 'Activo'): void
    {
        $this->unidades[$id] = ['id' => $id, 'codigo' => 'UO-'.str_pad((string) $id, 3, '0', STR_PAD_LEFT), 'nombre' => $nombre, 'estado' => $estado];
    }

    public function activas(): array
    {
        return array_values(array_filter($this->unidades, fn (array $u) => $u['estado'] === 'Activo'));
    }

    public function porIds(array $ids): array
    {
        return array_intersect_key($this->unidades, array_flip($ids));
    }
}
