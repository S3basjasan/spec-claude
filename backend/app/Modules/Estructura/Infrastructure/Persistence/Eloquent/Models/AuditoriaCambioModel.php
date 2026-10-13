<?php

namespace App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Entrada inmutable del historial de cambios: solo se inserta, nunca se modifica ni se borra desde la aplicación.
 */
class AuditoriaCambioModel extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'auditoria_cambios';

    protected $fillable = ['entidad', 'entidad_id', 'campo', 'valor_anterior', 'valor_nuevo', 'user_id'];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('El historial de cambios no se puede modificar.');
        });

        static::deleting(function () {
            throw new LogicException('El historial de cambios no se puede borrar.');
        });
    }
}
