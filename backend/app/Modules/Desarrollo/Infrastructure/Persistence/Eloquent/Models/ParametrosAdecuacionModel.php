<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/** Pesos por dimensión y umbral de preparación vigentes: una fila por tenant. */
class ParametrosAdecuacionModel extends Model
{
    protected $table = 'adecuacion_parametros';

    protected $guarded = [];
}
