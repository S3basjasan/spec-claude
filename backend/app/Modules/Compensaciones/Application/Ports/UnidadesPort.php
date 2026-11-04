<?php

namespace App\Modules\Compensaciones\Application\Ports;

/**
 * Lectura de las unidades organizacionales (módulo Estructura) a través de su contrato público.
 * Cada unidad es `{id, codigo, nombre, estado}` con estado `Activo` o `Inactivo`.
 */
interface UnidadesPort
{
    /**
     * Unidades activas en orden jerárquico.
     *
     * @return array<int, array{id: int, codigo: string, nombre: string, estado: string}>
     */
    public function activas(): array;

    /**
     * Unidades existentes entre los ids (activas e inactivas), indexadas por id.
     *
     * @param  array<int, int>  $ids
     * @return array<int, array{id: int, codigo: string, nombre: string, estado: string}>
     */
    public function porIds(array $ids): array;
}
