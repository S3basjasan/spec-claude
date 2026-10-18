<?php

namespace App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\PosicionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Posición de la estructura organizacional: un lugar concreto con puesto, unidad y supervisor. El código y el ocupante
 * (`user_id`) no son asignables en masa: el código lo fija el repositorio al crear y el ocupante solo lo escribe el módulo
 * de gestión de personal.
 */
class PosicionModel extends Model
{
    use HasFactory;

    protected $table = 'posiciones';

    protected $fillable = [
        'puesto_id', 'unidad_organizacional_id', 'posicion_jefe_id', 'estado', 'modalidad', 'version', 'created_by', 'updated_by', 'tenant_id',
    ];

    /** Código `POS-` + id con al menos 4 dígitos (`POS-0007`, `POS-10000`). */
    public static function codigoDeId(int $id): string
    {
        return sprintf('POS-%04d', $id);
    }

    public function puesto(): BelongsTo
    {
        return $this->belongsTo(PuestoModel::class, 'puesto_id');
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(UnidadModel::class, 'unidad_organizacional_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'posicion_jefe_id');
    }

    public function subordinadas(): HasMany
    {
        return $this->hasMany(self::class, 'posicion_jefe_id');
    }

    protected static function newFactory(): PosicionFactory
    {
        return PosicionFactory::new();
    }
}
