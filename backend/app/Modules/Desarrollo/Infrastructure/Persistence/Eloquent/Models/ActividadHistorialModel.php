<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/** Historial de cambios de asistencia y calificación de una actividad: solo se inserta. */
class ActividadHistorialModel extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'actividad_asistencia_historial';

    protected $guarded = [];
}
