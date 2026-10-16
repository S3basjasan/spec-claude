<?php

namespace App\Modules\Desarrollo\Application\Ports;

/**
 * Nombres de las entidades externas que muestra la DNF, resueltos por lote. Lo que no se encuentra no aparece
 * en el resultado y quien lo usa muestra «No disponible».
 */
interface NombresDeReferenciaInterface
{
    /**
     * @param  list<int>  $ids
     * @return array<int, string> colaborador_id => nombre completo
     */
    public function colaboradores(int $tenantId, array $ids): array;

    /**
     * @param  list<int>  $ids
     * @return array<int, array{codigo: string, nombre: string}>
     */
    public function puestos(array $ids): array;

    /**
     * @param  list<int>  $ids
     * @return array<int, array{codigo: string, nombre: string, activa: bool}>
     */
    public function competencias(array $ids): array;

    /**
     * @param  list<int>  $ids
     * @return array<int, string> unidad_id => nombre
     */
    public function unidades(array $ids): array;
}
