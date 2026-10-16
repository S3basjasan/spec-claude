<?php

namespace App\Modules\Desarrollo\Infrastructure\Adapters;

use App\Modules\Desarrollo\Application\Ports\ColaboradoresVigentesInterface;
use App\Modules\GestionPersonal\Presentation\Facades\PersonalFacade;

/** Obtiene los colaboradores vigentes por la Facade pública de Gestión de Personal. */
final class ColaboradoresVigentesDesdePersonal implements ColaboradoresVigentesInterface
{
    public function buscar(int $tenantId, ?string $texto, int $pagina, int $porPagina, string $fecha): array
    {
        return PersonalFacade::buscarColaboradoresVigentes($tenantId, $texto, $pagina, $porPagina, $fecha);
    }

    public function vigentesPorIds(int $tenantId, array $ids, string $fecha): array
    {
        return $ids === [] ? [] : PersonalFacade::vigentesPorIds($tenantId, $ids, $fecha);
    }
}
