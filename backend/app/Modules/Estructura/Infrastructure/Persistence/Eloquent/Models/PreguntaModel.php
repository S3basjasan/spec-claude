<?php

namespace App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreguntaModel extends Model
{
    protected $table = 'competencia_preguntas';

    protected $fillable = ['registro_id', 'orden', 'pregunta', 'created_by', 'updated_by'];

    public function registro(): BelongsTo
    {
        return $this->belongsTo(RegistroPreguntasModel::class, 'registro_id');
    }
}
