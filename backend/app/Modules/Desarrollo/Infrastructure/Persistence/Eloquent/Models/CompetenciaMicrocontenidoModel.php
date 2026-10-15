<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetenciaMicrocontenidoModel extends Model
{
    protected $table = 'competencia_microcontenido';

    protected $fillable = ['competencia_id', 'microcontenido_id', 'created_by'];

    public function microcontenido(): BelongsTo
    {
        return $this->belongsTo(MicrocontenidoModel::class, 'microcontenido_id');
    }
}
