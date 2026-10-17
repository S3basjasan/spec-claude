<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/** Historial de estados de un micro-contenido: solo se inserta. */
class MicroHistorialModel extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'microcontenido_historial';

    protected $fillable = ['microcontenido_id', 'estado_anterior', 'estado_nuevo', 'user_id'];
}
