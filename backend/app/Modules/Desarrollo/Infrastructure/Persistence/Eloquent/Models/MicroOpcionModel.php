<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class MicroOpcionModel extends Model
{
    protected $table = 'microcontenido_opciones';

    protected $fillable = ['pregunta_id', 'texto', 'es_correcta', 'orden'];

    protected function casts(): array
    {
        return ['es_correcta' => 'boolean'];
    }
}
