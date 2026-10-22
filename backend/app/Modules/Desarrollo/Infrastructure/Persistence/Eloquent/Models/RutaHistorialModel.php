<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/** Historial de cambios de rutas, niveles e inscripciones: solo se inserta. */
class RutaHistorialModel extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ruta_historial';

    protected $guarded = [];

    protected $casts = ['valor_anterior' => 'array', 'valor_nuevo' => 'array'];
}
