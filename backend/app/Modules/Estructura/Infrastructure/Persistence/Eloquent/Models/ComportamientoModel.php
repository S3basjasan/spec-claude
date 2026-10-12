<?php

namespace App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComportamientoModel extends Model
{
    protected $table = 'competencia_comportamientos';

    protected $fillable = ['competencia_grado_id', 'orden', 'descripcion', 'created_by', 'updated_by'];

    public function grado(): BelongsTo
    {
        return $this->belongsTo(CompetenciaGradoModel::class, 'competencia_grado_id');
    }
}
