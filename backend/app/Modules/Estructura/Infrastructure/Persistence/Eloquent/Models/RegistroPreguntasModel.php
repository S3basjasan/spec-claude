<?php

namespace App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\RegistroPreguntasFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cabecera del registro de preguntas de una competencia (auditoría, versión y unicidad).
 * No guarda estado ni datos de la competencia.
 */
class RegistroPreguntasModel extends Model
{
    use HasFactory;

    protected $table = 'competencia_pregunta_registros';

    protected $fillable = ['competencia_id', 'version', 'created_by', 'updated_by'];

    public function competencia(): BelongsTo
    {
        return $this->belongsTo(CompetenciaModel::class, 'competencia_id');
    }

    public function preguntas(): HasMany
    {
        return $this->hasMany(PreguntaModel::class, 'registro_id')->orderBy('orden');
    }

    protected static function newFactory(): RegistroPreguntasFactory
    {
        return RegistroPreguntasFactory::new();
    }
}
