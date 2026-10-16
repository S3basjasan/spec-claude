<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class DnfDetalleModel extends Model
{
    public $timestamps = false;

    protected $table = 'dnf_detalles';

    protected $fillable = [
        'dnf_colaborador_id', 'competencia_id', 'tipo_competencia', 'nivel_requerido', 'nivel_actual',
        'diferencia', 'brecha_porcentaje', 'prioridad', 'estado',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Una DNF es inmutable.');
        });
        static::deleting(function (): never {
            throw new LogicException('Una DNF no se elimina.');
        });
    }
}
