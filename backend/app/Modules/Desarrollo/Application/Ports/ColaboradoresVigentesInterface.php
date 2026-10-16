<?php

namespace App\Modules\Desarrollo\Application\Ports;

/**
 * Colaboradores con vínculo laboral vigente que pueden ser participantes de una acción formativa. Los aporta
 * Gestión de Personal a través de su Facade; este módulo no accede a sus tablas.
 */
interface ColaboradoresVigentesInterface
{
    /**
     * @return array{items: list<array{id: int, codigo: string, nombre: string}>, total: int}
     */
    public function buscar(int $tenantId, ?string $texto, int $pagina, int $porPagina, string $fecha): array;

    /**
     * Ids, entre los dados, con vínculo vigente en la fecha.
     *
     * @param  list<int>  $ids
     * @return list<int>
     */
    public function vigentesPorIds(int $tenantId, array $ids, string $fecha): array;
}
