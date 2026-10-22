<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models;

use App\Modules\Desarrollo\Domain\Exceptions\AdecuacionInmutable;
use Illuminate\Database\Eloquent\Model;

/** Resultado de un requisito de una adecuación: solo se inserta. */
class AdecuacionDetalleModel extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'adecuacion_detalles';

    protected $guarded = [];

    protected $casts = [
        'requerido_actual_anios' => 'float',
        'requerido_objetivo_anios' => 'float',
        'valor_anios' => 'float',
        'diferencia' => 'float',
        'tiempo_cierre_meses' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new AdecuacionInmutable);
        static::deleting(fn () => throw new AdecuacionInmutable);
    }
}
