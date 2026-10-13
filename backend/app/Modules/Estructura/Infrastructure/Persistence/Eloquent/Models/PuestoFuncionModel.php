<?php

namespace App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PuestoFuncionModel extends Model
{
    protected $table = 'puesto_funciones';

    public $timestamps = false;

    protected $fillable = ['puesto_id', 'descripcion', 'tipo', 'orden'];

    public function puesto(): BelongsTo
    {
        return $this->belongsTo(PuestoModel::class, 'puesto_id');
    }
}
