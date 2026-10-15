<?php

namespace App\Modules\Desarrollo\Application\Ports;

/**
 * Lectura del diccionario de competencias, cuyo unico dueño es el modulo Estructura.
 * El adaptador usa la Facade publica de Estructura; este modulo nunca accede a sus tablas.
 */
interface CompetenciasInterface
{
    /**
     * Todas las competencias (activas e inactivas), sin grados, por nombre.
     *
     * @return array<int, array{id: int, codigo: string, nombre: string, descripcion: string, tipo: string, estado: string}>
     */
    public function todas(): array;

    /**
     * Competencia con sus grados A-D, o null si no existe.
     *
     * @return array{id: int, codigo: string, nombre: string, descripcion: string, tipo: string, estado: string, grados: array<string, string>}|null
     */
    public function obtener(int $id): ?array;

    /**
     * @param  array<int, int>  $ids
     * @return array<int, array{id: int, codigo: string, nombre: string, descripcion: string, tipo: string, estado: string}>
     */
    public function porIds(array $ids): array;

    public function existe(int $id): bool;

    public function estaActiva(int $id): bool;
}
