<?php

namespace App\Modules\GestionPersonal\Application\Ports;

use App\Modules\GestionPersonal\Domain\Exceptions\IngresoNoVerificable;

/**
 * Posiciones que admiten nuevas asignaciones, según Estructura. `verificar` se evalúa en la conexión y la
 * transacción de quien llama; los fallos de lectura se lanzan como `IngresoNoVerificable`.
 */
interface PosicionesAsignablesPort
{
    /**
     * @return array{items: list<array{id: int, puesto: string, unidad: string, jefatura: string|null}>, total: int}
     *
     * @throws IngresoNoVerificable
     */
    public function listar(int $tenantId, ?string $texto, int $pagina, int $porPagina): array;

    /**
     * @return array{asignable: bool, motivo: string|null, posicion: array{id: int, puesto_id: int, puesto: string, unidad: string|null, jefatura: string|null}|null}
     *
     * @throws IngresoNoVerificable
     */
    public function verificar(int $posicionId, int $tenantId): array;

    /**
     * Datos de presentación de una posición (también suprimida), sin juzgar si admite asignaciones; null si no existe.
     *
     * @return array{id: int, puesto_id: int, puesto: string, unidad: string|null, jefatura: string|null}|null
     *
     * @throws IngresoNoVerificable
     */
    public function describir(int $posicionId): ?array;
}
