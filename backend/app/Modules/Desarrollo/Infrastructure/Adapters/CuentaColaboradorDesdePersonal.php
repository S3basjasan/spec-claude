<?php

namespace App\Modules\Desarrollo\Infrastructure\Adapters;

use App\Modules\Desarrollo\Application\Ports\CuentaColaboradorInterface;
use App\Modules\GestionPersonal\Presentation\Facades\PersonalFacade;

/** Ficha asociada a la cuenta, obtenida solo por la Facade pública de Gestión de Personal. */
final class CuentaColaboradorDesdePersonal implements CuentaColaboradorInterface
{
    public function cuentaDe(int $usuarioId): ?array
    {
        return PersonalFacade::getCuentaDeUsuario($usuarioId);
    }
}
