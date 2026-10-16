<?php

namespace App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\GestionPersonal\Application\DTO\AccesoDeCuenta;
use App\Modules\GestionPersonal\Application\Ports\AccesosDeCuentaPort;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\ColaboradorCuentaModel;

/** Lectura de solo lectura de la asociación cuenta-ficha y sus permisos (datos iniciales). */
final class EloquentAccesosDeCuenta implements AccesosDeCuentaPort
{
    public function deCuenta(int $userId): ?AccesoDeCuenta
    {
        $fila = ColaboradorCuentaModel::query()->where('user_id', $userId)->first();

        if ($fila === null) {
            return null;
        }

        return new AccesoDeCuenta(
            (int) $fila->tenant_id,
            (int) $fila->colaborador_id,
            (bool) $fila->puede_consultar_ficha,
            (bool) $fila->puede_consultar_documentos,
            (bool) $fila->puede_exportar,
        );
    }
}
