<?php

namespace App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\RegistroComportamientosFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cabecera del registro de comportamientos de una competencia (auditoría, versión y unicidad).
 * No guarda estado ni datos de la competencia.
 */
class RegistroComportamientosModel extends Model
{
    use HasFactory;

    protected $table = 'competencia_comportamiento_registros';

    protected $fillable = ['competencia_id', 'version', 'created_by', 'updated_by'];

    public function competencia(): BelongsTo
    {
        return $this->belongsTo(CompetenciaModel::class, 'competencia_id');
    }

    protected static function newFactory(): RegistroComportamientosFactory
    {
        return RegistroComportamientosFactory::new();
    }
}
