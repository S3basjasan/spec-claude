<?php

namespace App\Modules\Compensaciones\Infrastructure\Adapters;

use App\Modules\Compensaciones\Application\Ports\UnidadesPort;
use App\Modules\Estructura\Presentation\Facades\EstructuraFacade;

/** Lee las unidades solo a través de la Facade pública de Estructura: sin lectura de sus tablas ni copias. */
class UnidadesDesdeEstructura implements UnidadesPort
{
    public function activas(): array
    {
        return array_values(array_map(
            fn (array $u) => [
                'id' => (int) $u['id'],
                'codigo' => (string) $u['codigo'],
                'nombre' => (string) $u['nombre'],
                'estado' => 'Activo',
            ],
            EstructuraFacade::getUnidadesActivas(),
        ));
    }

    public function porIds(array $ids): array
    {
        $resultado = [];

        foreach (EstructuraFacade::getUnidadesPorIds(array_values(array_unique($ids))) as $u) {
            $resultado[(int) $u['id']] = [
                'id' => (int) $u['id'],
                'codigo' => (string) $u['codigo'],
                'nombre' => (string) $u['nombre'],
                // Estructura nombra los estados de unidad «Activa» / «Inactiva»; el módulo usa «Activo» / «Inactivo».
                'estado' => (string) $u['estado'] === 'Activa' ? 'Activo' : 'Inactivo',
            ];
        }

        return $resultado;
    }
}
