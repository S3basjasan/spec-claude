<?php

namespace App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompetenciaGradoModel extends Model
{
    protected $table = 'competencia_grados';

    protected $fillable = ['competencia_id', 'tipo_grado_valor_id', 'descripcion', 'created_by', 'updated_by'];

    public function competencia(): BelongsTo
    {
        return $this->belongsTo(CompetenciaModel::class, 'competencia_id');
    }

    /** Posición del grado en la escala (orden 1 = el más alto). */
    public function valor(): BelongsTo
    {
        return $this->belongsTo(TipoGradoValorModel::class, 'tipo_grado_valor_id');
    }

    public function comportamientos(): HasMany
    {
        return $this->hasMany(ComportamientoModel::class, 'competencia_grado_id')->orderBy('orden');
    }
}
