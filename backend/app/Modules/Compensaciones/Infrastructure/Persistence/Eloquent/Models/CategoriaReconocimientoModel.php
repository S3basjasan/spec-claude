<?php

namespace App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\CategoriaReconocimientoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoriaReconocimientoModel extends Model
{
    use HasFactory;

    protected $table = 'categorias_reconocimiento';

    /** Microsegundos: `updated_at` es el token de concurrencia. */
    protected $dateFormat = 'Y-m-d H:i:s.u';

    /** Sin soft delete ni borrado: el estado es un campo de negocio. */
    protected $fillable = [
        'codigo', 'nombre', 'nombre_normalizado', 'descripcion', 'competencia_id',
        'puntuacion_base', 'icono', 'estado', 'created_by', 'updated_by',
    ];

    public function indicadores(): HasMany
    {
        return $this->hasMany(IndicadorReconocimientoModel::class, 'categoria_reconocimiento_id')->orderBy('orden');
    }

    protected static function newFactory(): CategoriaReconocimientoFactory
    {
        return CategoriaReconocimientoFactory::new();
    }
}
