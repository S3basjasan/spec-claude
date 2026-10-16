<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Renglón del historial de un plan: solo se agrega, nunca se modifica ni se elimina. */
class PlanHistorialModel extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'plan_historial';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('El historial del plan no se modifica.');
        });
        static::deleting(function (): never {
            throw new LogicException('El historial del plan no se elimina.');
        });
    }
}
