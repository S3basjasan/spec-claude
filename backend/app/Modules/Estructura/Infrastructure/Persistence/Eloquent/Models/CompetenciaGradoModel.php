<?php

namespace App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompetenciaGradoModel extends Model
{
    protected $table = 'competencia_grados';

    public $timestamps = false;

    protected $fillable = ['competencia_id', 'grado', 'descripcion'];

    public function competencia(): BelongsTo
    {
        return $this->belongsTo(CompetenciaModel::class, 'competencia_id');
    }

    public function comportamientos(): HasMany
    {
        return $this->hasMany(ComportamientoModel::class, 'competencia_grado_id')->orderBy('orden');
    }
}
