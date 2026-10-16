<?php

namespace App\Modules\GestionPersonal\Application\Ports;

use App\Modules\GestionPersonal\Application\DTO\AccesoDeCuenta;

interface AccesosDeCuentaPort
{
    public function deCuenta(int $userId): ?AccesoDeCuenta;
}
