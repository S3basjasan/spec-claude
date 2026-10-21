<?php

namespace App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreguntaModel extends Model
{
    protected $table = 'competencia_preguntas';

    protected $fillable = ['competencia_id', 'orden', 'pregunta', 'created_by', 'updated_by'];

    public function competencia(): BelongsTo
    {
        return $this->belongsTo(CompetenciaModel::class, 'competencia_id');
    }
}
