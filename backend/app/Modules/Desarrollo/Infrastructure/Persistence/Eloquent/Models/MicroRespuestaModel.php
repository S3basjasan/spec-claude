<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class MicroRespuestaModel extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'microcontenido_respuestas';

    protected $fillable = ['intento_id', 'pregunta_id', 'opcion_id', 'es_correcta'];

    protected function casts(): array
    {
        return ['es_correcta' => 'boolean'];
    }
}
