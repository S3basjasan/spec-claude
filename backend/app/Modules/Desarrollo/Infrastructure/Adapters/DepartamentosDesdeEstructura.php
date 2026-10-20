<?php

namespace App\Modules\Desarrollo\Infrastructure\Adapters;

use App\Modules\Desarrollo\Application\Ports\DepartamentosInterface;
use App\Modules\Estructura\Presentation\Facades\EstructuraFacade;

/** Departamentos = unidades organizacionales de Estructura Empresarial, por su Facade pública. */
final class DepartamentosDesdeEstructura implements DepartamentosInterface
{
    public function activos(): array
    {
        return array_values(array_map(
            fn (array $u) => ['id' => (int) $u['id'], 'nombre' => (string) $u['nombre']],
            EstructuraFacade::getUnidadesActivas(),
        ));
    }

    public function porIds(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }
        $nombres = [];
        foreach (EstructuraFacade::getUnidadesPorIds($ids) as $u) {
            $nombres[(int) $u['id']] = (string) $u['nombre'];
        }

        return $nombres;
    }

    public function estaActivo(int $id): bool
    {
        return EstructuraFacade::unidadEstaActiva($id);
    }
}
