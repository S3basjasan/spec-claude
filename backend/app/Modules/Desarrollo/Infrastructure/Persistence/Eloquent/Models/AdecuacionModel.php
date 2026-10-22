<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models;

use App\Modules\Desarrollo\Domain\Exceptions\AdecuacionInmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Adecuación a puesto futuro: una vez creada no se actualiza ni se elimina (FR-018). */
class AdecuacionModel extends Model
{
    protected $table = 'adecuaciones';

    protected $guarded = [];

    protected $casts = ['experiencia_requerida_anios' => 'float'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new AdecuacionInmutable);
        static::deleting(fn () => throw new AdecuacionInmutable);
    }

    /** @return HasMany<AdecuacionDetalleModel, $this> */
    public function detalles(): HasMany
    {
        return $this->hasMany(AdecuacionDetalleModel::class, 'adecuacion_id')->orderBy('id');
    }
}
