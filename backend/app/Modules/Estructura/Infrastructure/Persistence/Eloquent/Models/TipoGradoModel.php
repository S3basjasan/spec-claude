<?php

namespace App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Escala del catálogo con la que se nombran los grados (Letras, Números o Categorías).
 */
class TipoGradoModel extends Model
{
    protected $table = 'tipo_grados';

    public $timestamps = false;

    protected $fillable = ['nombre', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function valores(): HasMany
    {
        return $this->hasMany(TipoGradoValorModel::class, 'tipo_grado_id')->orderBy('orden');
    }
}
