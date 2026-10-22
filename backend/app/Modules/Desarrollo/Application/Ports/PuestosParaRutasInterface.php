<?php

namespace App\Modules\Desarrollo\Application\Ports;

/**
 * Puestos que ofrece Estructura Empresarial a las rutas de carrera. Solo expone competencias con grado y los
 * requisitos de texto del puesto (Formación, Capacitación y Experiencia); no hay conocimientos con grado.
 */
interface PuestosParaRutasInterface
{
    /**
     * @return array{puesto: array{id: int, codigo: string, nombre: string, activo: bool}, competencias: list<array{competencia_id: int, codigo: string, nombre: string, tipo: string, grado: string}>, requisitos: array{formacion: list<string>, capacitacion: list<string>, experiencia: list<string>}}|null
     */
    public function perfil(int $puestoId): ?array;

    /** @return list<array{id: int, codigo: string, nombre: string}> puestos activos ordenados por nombre */
    public function activos(): array;

    public function estaActivo(int $puestoId): bool;
}
