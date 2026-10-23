<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class PdiAccionModel extends Model
{
    protected $table = 'pdi_acciones';

    protected $guarded = [];

    protected $casts = [
        'fecha_inicio' => 'date:Y-m-d',
        'fecha_fin' => 'date:Y-m-d',
        'estado_congelado' => 'boolean',
    ];
}
