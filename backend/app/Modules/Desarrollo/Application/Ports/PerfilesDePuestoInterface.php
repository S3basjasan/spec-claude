<?php

namespace App\Modules\Desarrollo\Application\Ports;

/**
 * Perfil de competencias de un puesto (lo aporta Estructura Empresarial).
 */
interface PerfilesDePuestoInterface
{
    /**
     * @return array{puesto: array{id: int, codigo: string, nombre: string}, competencias: list<array{competencia_id: int, codigo: string, nombre: string, tipo: string, activa: bool, nivel_requerido: string}>}|null
     */
    public function perfilDe(int $puestoId): ?array;
}
