<?php

namespace App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Registro inmutable de un cambio de escala: solo se inserta, nunca se modifica ni se borra.
 */
class CompetenciaEscalaHistorialModel extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'competencia_escala_historial';

    protected $fillable = ['competencia_id', 'escala_anterior', 'escala_nueva', 'user_id'];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('El historial de escalas no se puede modificar.');
        });

        static::deleting(function () {
            throw new LogicException('El historial de escalas no se puede borrar.');
        });
    }

    public function competencia(): BelongsTo
    {
        return $this->belongsTo(CompetenciaModel::class, 'competencia_id');
    }
}
