<?php

namespace App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Competencia asignada a un puesto con su grado requerido. No guarda datos de la competencia: se leen del diccionario.
 */
class PuestoCompetenciaModel extends Model
{
    protected $table = 'puesto_competencias';

    public $timestamps = false;

    protected $fillable = ['puesto_id', 'competencia_id', 'grado_requerido'];

    public function puesto(): BelongsTo
    {
        return $this->belongsTo(PuestoModel::class, 'puesto_id');
    }

    public function competencia(): BelongsTo
    {
        return $this->belongsTo(CompetenciaModel::class, 'competencia_id');
    }
}
