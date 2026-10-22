<?php

namespace App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class IndicadorReconocimientoModel extends Model
{
    protected $table = 'indicadores_reconocimiento';

    protected $fillable = ['categoria_reconocimiento_id', 'orden', 'texto', 'texto_normalizado'];
}
