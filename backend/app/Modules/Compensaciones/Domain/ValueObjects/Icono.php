<?php

namespace App\Modules\Compensaciones\Domain\ValueObjects;

/**
 * Lista cerrada de íconos del tablero «Categorías de reconocimiento». Se guarda el identificador.
 */
enum Icono: string
{
    case Estrella = 'estrella';
    case Trofeo = 'trofeo';
    case Corazon = 'corazon';
    case Bombilla = 'bombilla';
    case Rayo = 'rayo';
    case Escudo = 'escudo';
    case Equipo = 'equipo';
    case Objetivo = 'objetivo';
    case Mensaje = 'mensaje';
    case Libro = 'libro';
    case Bandera = 'bandera';
    case Diamante = 'diamante';

    /**
     * @return array<int, string>
     */
    public static function valores(): array
    {
        return array_map(fn (self $icono) => $icono->value, self::cases());
    }
}
