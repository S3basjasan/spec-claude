<?php

namespace App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PuestoRequisitoModel extends Model
{
    protected $table = 'puesto_requisitos';

    public $timestamps = false;

    protected $fillable = ['puesto_id', 'tipo', 'descripcion'];

    public function puesto(): BelongsTo
    {
        return $this->belongsTo(PuestoModel::class, 'puesto_id');
    }
}
