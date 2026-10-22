<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

/** Claves estables de permiso de la adecuación a puesto futuro y el mapa de talento. */
enum ClaveDePermisoAdecuacion: string
{
    case Generar = 'desarrollo.adecuacion.generar';
    case Ver = 'desarrollo.adecuacion.ver';
    case Comparar = 'desarrollo.adecuacion.comparar';
    case Mapa = 'desarrollo.adecuacion.mapa';
    case Parametros = 'desarrollo.adecuacion.parametros';
    case VerPropio = 'desarrollo.adecuacion.ver_propio';

    /** @return list<self> las cinco claves de gestión del administrador (sin `ver_propio`) */
    public static function deAdministrador(): array
    {
        return [self::Generar, self::Ver, self::Comparar, self::Mapa, self::Parametros];
    }

    /** @return list<self> las terminadas en `_propio`, que recibe el colaborador */
    public static function deColaborador(): array
    {
        return [self::VerPropio];
    }
}
