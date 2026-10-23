<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Historial del plan: solo inserciones, nunca se actualiza ni se elimina (FR-051). */
class PdiHistorialModel extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'pdi_historial';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('El historial del plan es de solo lectura.'));
        static::deleting(fn () => throw new LogicException('El historial del plan es de solo lectura.'));
    }
}
