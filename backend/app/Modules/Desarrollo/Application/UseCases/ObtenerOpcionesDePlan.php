<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\AprobadoresInterface;
use App\Modules\Desarrollo\Application\Ports\CompetenciasInterface;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoAccion;

/** Datos de los selectores del asistente: aprobadores, tipos y competencias activas (FR-008). */
final class ObtenerOpcionesDePlan
{
    public function __construct(
        private readonly AprobadoresInterface $aprobadores,
        private readonly CompetenciasInterface $competencias,
    ) {}

    /**
     * @return array{aprobadores: list<array{id: int, nombre: string}>, tipos: list<string>, competencias: list<array{id: int, codigo: string, nombre: string}>}
     */
    public function ejecutar(int $tenantId): array
    {
        $activas = array_values(array_filter($this->competencias->todas(), fn (array $c) => $c['estado'] === 'Activo'));

        return [
            'aprobadores' => $this->aprobadores->administradores($tenantId),
            'tipos' => array_map(fn (TipoAccion $t) => $t->value, TipoAccion::cases()),
            'competencias' => array_map(fn (array $c) => ['id' => $c['id'], 'codigo' => $c['codigo'], 'nombre' => $c['nombre']], $activas),
        ];
    }
}
