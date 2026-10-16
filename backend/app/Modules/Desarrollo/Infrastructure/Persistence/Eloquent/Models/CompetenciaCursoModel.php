<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetenciaCursoModel extends Model
{
    protected $table = 'competencia_curso';

    protected $fillable = ['competencia_id', 'curso_id', 'created_by'];

    public function curso(): BelongsTo
    {
        return $this->belongsTo(CursoModel::class, 'curso_id');
    }
}
