<?php

namespace App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nombre propio de uno de los cuatro grados de una competencia en la escala Categorías.
 */
class CategoriaModel extends Model
{
    protected $table = 'competencia_categorias';

    protected $fillable = ['competencia_id', 'orden', 'nombre', 'nombre_clave', 'created_by', 'updated_by'];

    public function competencia(): BelongsTo
    {
        return $this->belongsTo(CompetenciaModel::class, 'competencia_id');
    }
}
