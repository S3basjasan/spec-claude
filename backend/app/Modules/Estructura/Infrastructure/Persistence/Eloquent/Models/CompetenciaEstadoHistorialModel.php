<?php

namespace App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Registro inmutable de un cambio de estado: solo se inserta, nunca se modifica ni se borra.
 */
class CompetenciaEstadoHistorialModel extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'competencia_estado_historial';

    protected $fillable = ['competencia_id', 'estado_anterior', 'estado_nuevo', 'user_id'];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('El historial de estados no se puede modificar.');
        });

        static::deleting(function () {
            throw new LogicException('El historial de estados no se puede borrar.');
        });
    }

    public function competencia(): BelongsTo
    {
        return $this->belongsTo(CompetenciaModel::class, 'competencia_id');
    }
}
