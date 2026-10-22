<?php

namespace App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TipoGradoValorModel extends Model
{
    protected $table = 'tipo_grado_valores';

    public $timestamps = false;

    protected $fillable = ['tipo_grado_id', 'valor', 'orden'];

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoGradoModel::class, 'tipo_grado_id');
    }
}
