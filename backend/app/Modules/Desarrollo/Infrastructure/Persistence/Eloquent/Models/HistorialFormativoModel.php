<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/** Historial de solo inserción: no tiene updated_at ni se edita o elimina desde la aplicación. */
class HistorialFormativoModel extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'competencia_formativa_historial';

    protected $fillable = ['competencia_id', 'tipo_cambio', 'accion', 'recurso_id', 'valor_anterior', 'valor_nuevo', 'user_id'];
}
