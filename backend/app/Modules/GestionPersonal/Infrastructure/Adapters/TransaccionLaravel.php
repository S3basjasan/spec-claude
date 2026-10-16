<?php

namespace App\Modules\GestionPersonal\Infrastructure\Adapters;

use App\Modules\GestionPersonal\Application\Ports\TransaccionPort;
use Illuminate\Support\Facades\DB;

final class TransaccionLaravel implements TransaccionPort
{
    public function enTransaccion(callable $accion): mixed
    {
        return DB::transaction(fn () => $accion());
    }
}
