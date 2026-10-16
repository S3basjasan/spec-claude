<?php

namespace Tests\Support\Desarrollo;

use App\Modules\Desarrollo\Application\Ports\ColaboradoresVigentesInterface;

/** Colaboradores vigentes falsos: `[id => nombre]`; los ids no listados no tienen vínculo vigente. */
class ColaboradoresVigentesFalso implements ColaboradoresVigentesInterface
{
    /** @param array<int, string> $vigentes */
    public function __construct(public array $vigentes = []) {}

    public function buscar(int $tenantId, ?string $texto, int $pagina, int $porPagina, string $fecha): array
    {
        $items = [];
        foreach ($this->vigentes as $id => $nombre) {
            if ($texto === null || $texto === '' || stripos($nombre, $texto) !== false) {
                $items[] = ['id' => $id, 'codigo' => sprintf('COL-%04d', $id), 'nombre' => $nombre];
            }
        }

        return ['items' => array_slice($items, ($pagina - 1) * $porPagina, $porPagina), 'total' => count($items)];
    }

    public function vigentesPorIds(int $tenantId, array $ids, string $fecha): array
    {
        return array_values(array_filter($ids, fn (int $id) => isset($this->vigentes[$id])));
    }
}
