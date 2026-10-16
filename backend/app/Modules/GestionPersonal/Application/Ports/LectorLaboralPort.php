<?php

namespace App\Modules\GestionPersonal\Application\Ports;

use App\Modules\GestionPersonal\Domain\ValueObjects\Lectura;

/**
 * Lectura de vínculos y hechos laborales (GP-02). Los fallos se devuelven como Lectura::noDisponible(),
 * nunca como excepciones.
 */
interface LectorLaboralPort
{
    /**
     * @param  list<int>  $colaboradorIds
     * @return Lectura Datos: [colaborador_id => {estado: string, posicion_id: int|null}]
     */
    public function situacionVigente(int $tenantId, array $colaboradorIds, string $fecha): Lectura;

    /** @return Lectura Datos: list<int> ids de fichas con vínculo vigente */
    public function idsConVinculoVigente(int $tenantId, string $fecha): Lectura;

    /** @return Lectura Datos: list<int> ids de fichas con ese estado */
    public function idsPorEstado(int $tenantId, string $estado, string $fecha): Lectura;

    /**
     * @param  list<int>  $posicionIds
     * @return Lectura Datos: list<int> ids de fichas asignadas a esas posiciones
     */
    public function colaboradoresEnPosiciones(int $tenantId, array $posicionIds, string $fecha): Lectura;

    /** @return Lectura Datos: list<array{fecha_efectiva: string, tipo: string, detalle?: string|null}> */
    public function trayectoria(int $tenantId, int $colaboradorId): Lectura;
}
