<?php

namespace App\Modules\Desarrollo\Infrastructure\Adapters;

use App\Modules\Desarrollo\Application\Ports\PerfilesDePuestoInterface;
use App\Modules\Estructura\Presentation\Facades\EstructuraFacade;

/** Lee el perfil de competencias del puesto exclusivamente por la Facade pública de Estructura. */
final class PerfilesDePuestoDesdeEstructura implements PerfilesDePuestoInterface
{
    public function perfilDe(int $puestoId): ?array
    {
        $puesto = EstructuraFacade::getPuesto($puestoId);
        if ($puesto === null) {
            return null;
        }

        return [
            'puesto' => ['id' => (int) $puesto['id'], 'codigo' => (string) $puesto['codigo'], 'nombre' => (string) $puesto['nombre']],
            'competencias' => array_values(array_map(fn (array $c) => [
                'competencia_id' => (int) $c['competencia_id'],
                'codigo' => (string) $c['codigo'],
                'nombre' => (string) $c['nombre'],
                'tipo' => (string) $c['tipo'],
                'activa' => $c['estado'] === 'Activo',
                'nivel_requerido' => (string) $c['grado_requerido'],
            ], $puesto['competencias'])),
        ];
    }
}
