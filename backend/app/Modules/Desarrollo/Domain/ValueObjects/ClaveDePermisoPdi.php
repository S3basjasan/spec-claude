<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

/** Claves estables de permiso del plan de desarrollo individual. */
enum ClaveDePermisoPdi: string
{
    case Gestionar = 'desarrollo.pdi.gestionar';
    case Acordar = 'desarrollo.pdi.acordar';
    case Seguimiento = 'desarrollo.pdi.seguimiento';
    case Vincular = 'desarrollo.pdi.vincular';
    case Cerrar = 'desarrollo.pdi.cerrar';
    case Gantt = 'desarrollo.pdi.gantt';
    case Ver = 'desarrollo.pdi.ver';
    case VerPropio = 'desarrollo.pdi.ver_propio';
    case AceptarPropio = 'desarrollo.pdi.aceptar_propio';

    /** @return list<self> las siete claves de gestión del administrador (sin las terminadas en `_propio`) */
    public static function deAdministrador(): array
    {
        return [self::Gestionar, self::Acordar, self::Seguimiento, self::Vincular, self::Cerrar, self::Gantt, self::Ver];
    }

    /** @return list<self> las terminadas en `_propio`, que recibe el colaborador */
    public static function deColaborador(): array
    {
        return [self::VerPropio, self::AceptarPropio];
    }
}
