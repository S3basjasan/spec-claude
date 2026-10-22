<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

/** Claves estables de permiso del módulo de rutas de carrera. */
enum ClaveDePermisoRutas: string
{
    case Ver = 'desarrollo.rutas.ver';
    case Gestionar = 'desarrollo.rutas.gestionar';
    case Inscribir = 'desarrollo.rutas.inscribir';
    case Inscripciones = 'desarrollo.rutas.inscripciones';
    case Historial = 'desarrollo.rutas.historial';
    case VerPropio = 'desarrollo.rutas.ver_propio';

    /** @return list<self> las claves de gestión y consulta del administrador */
    public static function deAdministrador(): array
    {
        return [self::Ver, self::Gestionar, self::Inscribir, self::Inscripciones, self::Historial];
    }

    /** @return list<self> las terminadas en `_propio`, que recibe el colaborador */
    public static function deColaborador(): array
    {
        return [self::VerPropio];
    }
}
