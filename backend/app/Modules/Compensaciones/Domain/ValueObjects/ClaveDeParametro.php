<?php

namespace App\Modules\Compensaciones\Domain\ValueObjects;

/**
 * Los seis parámetros del módulo, con su rango y su valor por defecto. La sensibilidad del radar se guarda como
 * 1 (Baja), 2 (Normal) o 3 (Alta); los umbrales de cada opción viven en el código de quien los usa (07.2).
 */
enum ClaveDeParametro: string
{
    case PuntosPorColaborador = 'puntos_por_colaborador';
    case PlazoAnulacionDias = 'plazo_anulacion_dias';
    case UmbralConcentracion = 'umbral_concentracion';
    case PlazoMaxDesafioDias = 'plazo_max_desafio_dias';
    case UmbralClimaUnidad = 'umbral_clima_unidad';
    case SensibilidadRadar = 'sensibilidad_radar';

    public function minimo(): int
    {
        return 1;
    }

    public function maximo(): int
    {
        return match ($this) {
            self::PuntosPorColaborador => 1000,
            self::PlazoAnulacionDias => 30,
            self::UmbralConcentracion, self::UmbralClimaUnidad => 100,
            self::PlazoMaxDesafioDias => 90,
            self::SensibilidadRadar => 3,
        };
    }

    /** Valor por defecto fijo; el de los puntos por colaborador también depende de las categorías (lo resuelve el servicio). */
    public function porDefecto(): int
    {
        return match ($this) {
            self::PuntosPorColaborador => 100,
            self::PlazoAnulacionDias => 7,
            self::UmbralConcentracion => 50,
            self::PlazoMaxDesafioDias => 30,
            self::UmbralClimaUnidad => 33,
            self::SensibilidadRadar => 2,
        };
    }

    /** Solo los puntos por colaborador rigen desde el día 1 del mes siguiente; los demás, desde hoy. */
    public function esDiferida(): bool
    {
        return $this === self::PuntosPorColaborador;
    }

    public function admite(int $valor): bool
    {
        return $valor >= $this->minimo() && $valor <= $this->maximo();
    }

    /** @return array<int, string> */
    public static function valores(): array
    {
        return array_map(fn (self $clave) => $clave->value, self::cases());
    }
}
