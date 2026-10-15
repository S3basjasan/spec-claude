<?php

namespace App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\UnidadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnidadModel extends Model
{
    use HasFactory;

    protected $table = 'unidades_organizacionales';

    /** El código no es asignable en masa: lo fija el repositorio al crear y nunca cambia. */
    protected $fillable = [
        'nombre', 'tipo_unidad_id', 'unidad_padre_id', 'estado', 'fecha_creacion', 'fecha_supresion',
        'version', 'created_by', 'updated_by',
    ];

    /** Código `UO-` + id con al menos 3 dígitos (`UO-003`, `UO-1000`). */
    public static function codigoDeId(int $id): string
    {
        return sprintf('UO-%03d', $id);
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoUnidadModel::class, 'tipo_unidad_id');
    }

    public function padre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'unidad_padre_id');
    }

    public function dependientes(): HasMany
    {
        return $this->hasMany(self::class, 'unidad_padre_id');
    }

    public function posiciones(): HasMany
    {
        return $this->hasMany(PosicionModel::class, 'unidad_organizacional_id');
    }

    protected static function newFactory(): UnidadFactory
    {
        return UnidadFactory::new();
    }
}
