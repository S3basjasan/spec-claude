<?php

namespace App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\CategoriaReconocimientoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CategoriaReconocimientoModel extends Model
{
    use HasFactory;

    protected $table = 'categorias_reconocimiento';

    /** Microsegundos: `updated_at` es el token de concurrencia. */
    protected $dateFormat = 'Y-m-d H:i:s.u';

    /** Sin soft delete ni borrado: el estado es un campo de negocio. */
    protected $fillable = [
        'codigo', 'nombre', 'nombre_normalizado', 'descripcion', 'competencia_id',
        'puntuacion_base', 'icono', 'indicadores', 'estado', 'created_by', 'updated_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['indicadores' => 'array'];
    }

    protected static function newFactory(): CategoriaReconocimientoFactory
    {
        return CategoriaReconocimientoFactory::new();
    }
}
