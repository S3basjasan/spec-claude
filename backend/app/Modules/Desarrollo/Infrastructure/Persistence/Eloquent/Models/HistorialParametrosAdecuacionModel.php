<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/** Cambios de los parámetros de adecuación: solo se inserta. */
class HistorialParametrosAdecuacionModel extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'adecuacion_parametros_historial';

    protected $guarded = [];
}
