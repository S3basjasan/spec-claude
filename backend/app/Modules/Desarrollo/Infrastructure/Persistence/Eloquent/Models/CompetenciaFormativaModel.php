<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetenciaFormativaModel extends Model
{
    protected $table = 'competencia_formativa';

    protected $fillable = ['competencia_id', 'area_formativa_id', 'version', 'updated_by'];

    public function area(): BelongsTo
    {
        return $this->belongsTo(AreaFormativaModel::class, 'area_formativa_id');
    }
}
