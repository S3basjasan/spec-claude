<?php

namespace App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Plan de desarrollo individual. No se elimina nunca: los planes se cierran. */
class PdiModel extends Model
{
    protected $table = 'pdis';

    protected $guarded = [];

    protected $casts = [
        'fecha_inicio' => 'date:Y-m-d',
        'fecha_fin' => 'date:Y-m-d',
        'acuerdo_responsable_at' => 'datetime',
        'aceptacion_colaborador_at' => 'datetime',
        'cerrado_at' => 'datetime',
    ];

    /** @return HasMany<PdiCompromisoModel, $this> */
    public function compromisos(): HasMany
    {
        return $this->hasMany(PdiCompromisoModel::class, 'pdi_id')->orderBy('orden')->orderBy('id');
    }

    /** @return HasMany<PdiHitoModel, $this> */
    public function hitos(): HasMany
    {
        return $this->hasMany(PdiHitoModel::class, 'pdi_id')->orderBy('fecha')->orderBy('id');
    }
}
