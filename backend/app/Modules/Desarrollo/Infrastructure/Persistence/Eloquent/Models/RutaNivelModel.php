<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/** Nivel de una ruta de carrera: peldaño ordenado ligado a un puesto de Estructura Empresarial. */
class RutaNivelModel extends Model
{
    protected $table = 'ruta_niveles';

    protected $guarded = [];

    protected $casts = ['experiencia_minima_anios' => 'float'];
}
