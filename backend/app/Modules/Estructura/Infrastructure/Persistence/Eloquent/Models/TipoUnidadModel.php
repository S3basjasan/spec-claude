<?php

namespace App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\TipoUnidadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tipo de la cadena de tipos de unidad (`nivel` 1 = tipo raíz). Los tipos nunca se eliminan.
 */
class TipoUnidadModel extends Model
{
    use HasFactory;

    protected $table = 'tipos_unidad';

    protected $fillable = ['nombre', 'nivel', 'created_by', 'updated_by'];

    public function unidades(): HasMany
    {
        return $this->hasMany(UnidadModel::class, 'tipo_unidad_id');
    }

    protected static function newFactory(): TipoUnidadFactory
    {
        return TipoUnidadFactory::new();
    }
}
