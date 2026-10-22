<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/** Inscripción de un colaborador a una ruta. Nunca se elimina: se finaliza o se retira. */
class RutaInscripcionModel extends Model
{
    protected $table = 'ruta_inscripciones';

    protected $guarded = [];
}
