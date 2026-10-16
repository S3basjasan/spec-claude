<?php

namespace App\Modules\Estructura\Application\Ports;

/**
 * Ocupación de posiciones por asignaciones laborales (GP-02). La implementa un adaptador sobre el contrato
 * público de Gestión de Personal; Estructura no lee tablas de otro módulo.
 */
interface OcupacionDePosicionesPort
{
    /**
     * Posiciones, entre las dadas, con una asignación vigente o futura en la fecha (fin nulo o posterior).
     *
     * @param  array<int, int>  $posicionIds
     * @return array<int, int>
     */
    public function conAsignacionVigenteOFutura(array $posicionIds, string $fecha): array;
}
