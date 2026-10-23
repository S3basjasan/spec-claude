<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class PdiHitoModel extends Model
{
    protected $table = 'pdi_hitos';

    protected $guarded = [];

    protected $casts = [
        'fecha' => 'date:Y-m-d',
        'registrado_at' => 'datetime',
    ];
}
