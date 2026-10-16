<?php

namespace App\Modules\Desarrollo\Infrastructure\Adapters;

use App\Modules\Desarrollo\Application\Ports\NivelesDeColaboradorInterface;

/**
 * Adaptador PROVISIONAL: Gestion de Personal aun no existe, por lo que no hay niveles registrados.
 * Al implementarse ese modulo, se reemplaza por un adaptador que llame a su Facade y se cambia el binding.
 */
final class NivelesDeColaboradorSinDatos implements NivelesDeColaboradorInterface
{
    public function nivelesDe(int $usuarioId): array
    {
        return [];
    }
}
