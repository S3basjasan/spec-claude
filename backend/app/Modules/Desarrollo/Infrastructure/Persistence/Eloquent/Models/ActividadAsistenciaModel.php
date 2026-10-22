<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/** Registro de asistencia de un colaborador en una actividad. Sin fila equivale a asistencia Pendiente. */
class ActividadAsistenciaModel extends Model
{
    protected $table = 'actividad_asistencias';

    protected $guarded = [];
}
